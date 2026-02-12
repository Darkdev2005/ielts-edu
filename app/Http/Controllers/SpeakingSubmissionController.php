<?php

namespace App\Http\Controllers;

use App\Models\SpeakingPrompt;
use App\Models\SpeakingSubmission;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use App\Services\FeatureGate;
use App\Exceptions\LimitExceededException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SpeakingSubmissionController extends Controller
{
    public function store(
        Request $request,
        SpeakingPrompt $prompt,
        FeatureGate $featureGate,
        RateLimiterMySql $rateLimiter,
        AiRequestService $service
    )
    {
        abort_if(!$prompt->is_active || $prompt->mode !== 'practice', 404);

        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $canSpeaking = $user->is_admin || $featureGate->userCan($user, 'speaking_ai');

        $data = $request->validate([
            'response_text' => ['nullable', 'string'],
            'audio' => ['nullable', 'file', 'mimetypes:audio/webm,video/webm,audio/ogg,audio/mp4,audio/mpeg,audio/wav,audio/x-wav,audio/x-m4a,audio/aac,audio/3gpp', 'max:10240'],
        ], [
            'response_text.required' => __('app.writing_text_required'),
        ]);

        $responseText = trim((string) ($data['response_text'] ?? ''));
        $hasAudioFile = $request->hasFile('audio');
        if ($responseText === '' && !$hasAudioFile) {
            return back()->withErrors(['response_text' => __('app.writing_text_required')])->withInput();
        }
        if ($responseText !== '' && Str::length($responseText) < 10) {
            return back()->withErrors(['response_text' => __('app.writing_text_required')])->withInput();
        }
        $wordCount = Str::wordCount($responseText);

        $audioPath = null;
        $hasAudio = false;
        if ($hasAudioFile) {
            $audioPath = $request->file('audio')->store('speaking-audio', 'public');
            $hasAudio = true;
        }

        $submission = SpeakingSubmission::create([
            'user_id' => $user->id,
            'speaking_prompt_id' => $prompt->id,
            'response_text' => $responseText,
            'word_count' => $wordCount,
            'status' => 'queued',
            'audio_path' => $audioPath,
            'has_audio' => $hasAudio,
        ]);

        if (!$canSpeaking) {
            return redirect()->route('speaking.submissions.show', $submission)
                ->with('status', __('app.speaking_pro_hint'));
        }

        if ($hasAudio && $responseText === '') {
            return redirect()->route('speaking.submissions.show', $submission)
                ->with('status', 'Audio received. Transcript pending.');
        }

        $promptText = $this->buildSpeakingFeedbackPrompt($prompt, $responseText, $hasAudio);
        $context = [
            'prompt_id' => $prompt->id,
            'part' => $prompt->part,
            'difficulty' => $prompt->difficulty,
            'prompt' => $prompt->prompt,
            'response_text' => $responseText,
            'has_audio' => $hasAudio,
        ];

        $allowed = $rateLimiter->hit('user', (string) $submission->user_id, (int) config('ai.user_rpm', 20));
        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$allowed || !$globalAllowed) {
            $message = !$allowed ? 'User rate limit exceeded.' : 'Global rate limit exceeded.';
            $aiRequest = $service->logFailure(
                $submission->user_id,
                'speaking_feedback',
                $promptText,
                $context,
                ['temperature' => 0.3, 'max_output_tokens' => 800],
                $message,
            );
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'ai_request_id' => $aiRequest->id,
            ]);

            return redirect()->route('speaking.submissions.show', $submission)
                ->with('status', $message);
        }

        try {
            $aiRequest = $service->create(
                $submission->user_id,
                'speaking_feedback',
                $promptText,
                $context,
                ['temperature' => 0.3, 'max_output_tokens' => 800]
            );
        } catch (LimitExceededException $e) {
            $message = __('app.daily_limit_reached');
            $aiRequest = $service->logFailure(
                $submission->user_id,
                'speaking_feedback',
                $promptText,
                $context,
                ['temperature' => 0.3, 'max_output_tokens' => 800],
                $message,
            );
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'ai_request_id' => $aiRequest->id,
            ]);
            return redirect()->route('speaking.submissions.show', $submission)
                ->with('status', $message)
                ->with('upgrade_prompt', true);
        }

        $submission->update([
            'ai_request_id' => $aiRequest->id,
            'status' => $aiRequest->status === 'done' ? 'done' : 'queued',
        ]);

        if ($aiRequest->status === 'done') {
            $this->applySpeakingFeedbackFromRequest($submission, $aiRequest);
        }

        return redirect()->route('speaking.submissions.show', $submission);
    }

    public function show(SpeakingSubmission $submission, FeatureGate $featureGate)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('prompt');
        abort_if(!$submission->prompt || $submission->prompt->mode !== 'practice', 404);

        $user = Auth::user();
        $canSpeaking = $user && ($user->is_admin || $featureGate->userCan($user, 'speaking_ai'));
        $this->syncSubmissionAiStatus($submission);

        return view('speaking.submission', [
            'submission' => $submission,
            'canSpeaking' => $canSpeaking,
        ]);
    }

    public function status(SpeakingSubmission $submission)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('prompt');
        abort_if(!$submission->prompt || $submission->prompt->mode !== 'practice', 404);

        $this->syncSubmissionAiStatus($submission);
        $payload = $submission->ai_feedback_json;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $accuracyPercent = data_get($payload, 'accuracy_percent');
        if (!is_numeric($accuracyPercent) && is_numeric($submission->band_score)) {
            $accuracyPercent = max(0, min(100, (int) round((((float) $submission->band_score) / 9) * 100)));
        }

        return response()->json([
            'status' => $submission->status,
            'band_score' => $submission->band_score,
            'accuracy_percent' => is_numeric($accuracyPercent) ? (int) round((float) $accuracyPercent) : null,
            'ai_feedback' => $submission->ai_feedback,
            'ai_feedback_json' => $payload,
            'ai_error' => $submission->ai_error,
        ]);
    }

    private function syncSubmissionAiStatus(SpeakingSubmission $submission): void
    {
        if (!$submission->ai_request_id) {
            return;
        }

        $aiRequest = \App\Models\AiRequest::find($submission->ai_request_id);
        if (!$aiRequest) {
            return;
        }

        if ($aiRequest->status === 'done' && $submission->status !== 'done') {
            $this->applySpeakingFeedbackFromRequest($submission, $aiRequest);
        } elseif ($aiRequest->status === 'done' && $submission->status === 'done' && !$submission->ai_feedback_json) {
            $this->applySpeakingFeedbackFromRequest($submission, $aiRequest);
        } elseif ($aiRequest->status === 'processing' && $submission->status !== 'running') {
            $submission->update(['status' => 'running']);
        } elseif (in_array($aiRequest->status, ['failed', 'failed_quota'], true)) {
            $submission->update([
                'status' => 'failed',
                'ai_error' => $aiRequest->error_text,
            ]);
        }

        if ($submission->status === 'done' && is_array($submission->ai_feedback_json)) {
            $normalized = $this->normalizePracticePayload($submission->ai_feedback_json, (int) $submission->prompt?->part);
            $current = json_encode($submission->ai_feedback_json);
            $next = json_encode($normalized);
            if ($current !== $next || $submission->band_score !== null) {
                $submission->update([
                    'ai_feedback_json' => $normalized,
                    'band_score' => null,
                ]);
            }
        }
    }

    private function applySpeakingFeedbackFromRequest(SpeakingSubmission $submission, \App\Models\AiRequest $aiRequest): void
    {
        $text = (string) ($aiRequest->output_json['text'] ?? '');
        $parsed = $this->extractJson($text);
        $parsed = $this->normalizePracticePayload($parsed, (int) $submission->prompt?->part);

        $submission->update([
            'status' => 'done',
            'band_score' => null,
            'ai_feedback' => $parsed['summary'] ?? null,
            'ai_feedback_json' => $parsed ?: null,
            'ai_error' => null,
        ]);
    }

    private function buildSpeakingFeedbackPrompt(SpeakingPrompt $prompt, string $responseText, bool $hasAudio): string
    {
        $taskType = 'speaking';
        $part = $prompt->part;
        $cefr = $prompt->difficulty ?: '';
        $hasAudioFlag = $hasAudio ? 'true' : 'false';
        $languageInstruction = $this->feedbackLanguageInstruction();

        return "You are IELTS EDU Speaking PRACTICE evaluator.\n"
            ."This is NOT an exam simulation.\n"
            ."Your role is to help the learner improve.\n\n"
            ."ABSOLUTE RULES:\n"
            ."- Do NOT assign IELTS band scores in practice mode.\n"
            ."- Do NOT use exam-style wording like real score or exam result.\n"
            ."- Give clear, supportive, instructional feedback.\n"
            ."- Be honest if the response is weak.\n\n"
            ."INPUT:\n"
            ."- module: speaking\n"
            ."- mode: practice\n"
            ."- task_type: {$taskType}\n"
            ."- part: {$part}\n"
            ."- cefr_level: {$cefr}\n"
            ."- task_prompt: {$prompt->prompt}\n"
            ."- user_answer: {$responseText}\n"
            ."- has_audio: {$hasAudioFlag}\n\n"
            ."PRONUNCIATION RULE:\n"
            ."- If has_audio is false: pronunciation notes must say text-only.\n"
            ."- Never return pronunciation band in practice mode.\n\n"
            ."LANGUAGE RULE:\n"
            ."- {$languageInstruction}\n\n"
            ."OUTPUT JSON SCHEMA:\n"
            ."{\n"
            ."  \"module\": \"speaking\",\n"
            ."  \"mode\": \"practice\",\n"
            ."  \"task_type\": \"{$taskType}\",\n"
            ."  \"cefr_level\": \"{$cefr}\",\n"
            ."  \"part\": {$part},\n"
            ."  \"accuracy_percent\": 0,\n"
            ."  \"overall_band\": null,\n"
            ."  \"criteria\": {\n"
            ."    \"task_response\": { \"band\": null, \"notes\": \"\" },\n"
            ."    \"coherence_cohesion\": { \"band\": null, \"notes\": \"\" },\n"
            ."    \"lexical_resource\": { \"band\": null, \"notes\": \"\" },\n"
            ."    \"grammar_range_accuracy\": { \"band\": null, \"notes\": \"\" },\n"
            ."    \"fluency_coherence\": { \"band\": null, \"notes\": \"\" },\n"
            ."    \"pronunciation\": { \"band\": null, \"notes\": \"\" }\n"
            ."  },\n"
            ."  \"strengths\": [\"\"],\n"
            ."  \"weaknesses\": [\"\"],\n"
            ."  \"next_steps\": [\"\", \"\", \"\"],\n"
            ."  \"corrections\": [\n"
            ."    {\"issue\": \"\", \"before\": \"\", \"after\": \"\"}\n"
            ."  ],\n"
            ."  \"examples\": [\"\"],\n"
            ."  \"summary\": \"\",\n"
            ."  \"upgrade_hint\": \"\"\n"
            ."}\n\n"
            ."FINAL CHECK:\n"
            ."- JSON must be valid.\n"
            ."- No IELTS band score anywhere.\n"
            ."- No extra text outside JSON.";
    }

    private function normalizePracticePayload(?array $parsed, int $part): ?array
    {
        if (!is_array($parsed)) {
            return $parsed;
        }

        $accuracy = $parsed['accuracy_percent'] ?? null;
        if (!is_numeric($accuracy)) {
            $legacyBand = $parsed['overall_band'] ?? null;
            if (is_numeric($legacyBand)) {
                $accuracy = ((float) $legacyBand / 9) * 100;
            } else {
                $accuracy = 0;
            }
        }

        $accuracy = is_numeric($accuracy)
            ? max(0, min(100, (int) round((float) $accuracy)))
            : 0;

        $parsed['module'] = 'speaking';
        $parsed['mode'] = 'practice';
        $parsed['part'] = $part;
        $parsed['accuracy_percent'] = $accuracy;
        $parsed['overall_band'] = null;

        foreach (['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy', 'fluency_coherence', 'pronunciation'] as $key) {
            if (!isset($parsed['criteria'][$key]) || !is_array($parsed['criteria'][$key])) {
                $parsed['criteria'][$key] = ['band' => null, 'notes' => ''];
                continue;
            }

            $parsed['criteria'][$key]['band'] = null;
            $parsed['criteria'][$key]['notes'] = (string) ($parsed['criteria'][$key]['notes'] ?? '');
        }

        return $parsed;
    }

    private function feedbackLanguageInstruction(): string
    {
        return match (app()->getLocale()) {
            'uz' => 'Write all feedback in Uzbek language.',
            'ru' => 'Write all feedback in Russian language.',
            default => 'Write all feedback in English language.',
        };
    }

    private function extractJson(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        $candidates = [$trimmed];
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($trimmed, $start, $end - $start + 1);
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
