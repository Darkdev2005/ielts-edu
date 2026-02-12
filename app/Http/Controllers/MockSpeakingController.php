<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitExceededException;
use App\Models\SpeakingPrompt;
use App\Models\SpeakingSubmission;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MockSpeakingController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $user = Auth::user();
        $canAccess = $this->canAccess($user, $featureGate);

        $prompts = SpeakingPrompt::query()
            ->where('is_active', true)
            ->where('mode', 'mock')
            ->orderBy('part')
            ->orderBy('id')
            ->get()
            ->groupBy('part');

        $recentSubmissions = collect();
        if ($user) {
            $recentSubmissions = SpeakingSubmission::query()
                ->where('user_id', $user->id)
                ->whereHas('prompt', fn ($query) => $query->where('mode', 'mock'))
                ->with('prompt')
                ->latest()
                ->limit(8)
                ->get();
        }

        return view('mock.speaking.index', [
            'promptsByPart' => $prompts,
            'canAccess' => $canAccess,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    public function show(SpeakingPrompt $prompt, FeatureGate $featureGate)
    {
        abort_if(!$prompt->is_active || $prompt->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            return redirect()
                ->route('mock.speaking.index')
                ->with('status', __('app.upgrade_required'));
        }

        $latestSubmission = SpeakingSubmission::query()
            ->where('user_id', Auth::id())
            ->where('speaking_prompt_id', $prompt->id)
            ->latest()
            ->first();

        return view('mock.speaking.show', [
            'prompt' => $prompt,
            'latestSubmission' => $latestSubmission,
        ]);
    }

    public function submit(
        Request $request,
        SpeakingPrompt $prompt,
        FeatureGate $featureGate,
        RateLimiterMySql $rateLimiter,
        AiRequestService $service
    ) {
        abort_if(!$prompt->is_active || $prompt->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            return redirect()
                ->route('mock.speaking.index')
                ->with('status', __('app.upgrade_required'));
        }

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
            'user_id' => Auth::id(),
            'speaking_prompt_id' => $prompt->id,
            'response_text' => $responseText,
            'word_count' => $wordCount,
            'status' => 'queued',
            'audio_path' => $audioPath,
            'has_audio' => $hasAudio,
        ]);

        if ($hasAudio && $responseText === '') {
            return redirect()->route('mock.speaking.result', $submission)
                ->with('status', 'Audio received. Transcript pending.');
        }

        $promptText = $this->buildMockSpeakingFeedbackPrompt($prompt, $responseText, $hasAudio);
        $context = [
            'prompt_id' => $prompt->id,
            'part' => $prompt->part,
            'difficulty' => $prompt->difficulty,
            'prompt' => $prompt->prompt,
            'response_text' => $responseText,
            'has_audio' => $hasAudio,
            'mode' => 'mock',
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
                ['temperature' => 0.3, 'max_output_tokens' => 900],
                $message,
            );
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'ai_request_id' => $aiRequest->id,
            ]);

            return redirect()->route('mock.speaking.result', $submission)
                ->with('status', $message);
        }

        try {
            $aiRequest = $service->create(
                $submission->user_id,
                'speaking_feedback',
                $promptText,
                $context,
                ['temperature' => 0.3, 'max_output_tokens' => 900]
            );
        } catch (LimitExceededException $e) {
            $message = __('app.daily_limit_reached');
            $aiRequest = $service->logFailure(
                $submission->user_id,
                'speaking_feedback',
                $promptText,
                $context,
                ['temperature' => 0.3, 'max_output_tokens' => 900],
                $message,
            );
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'ai_request_id' => $aiRequest->id,
            ]);

            return redirect()->route('mock.speaking.result', $submission)
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

        return redirect()->route('mock.speaking.result', $submission);
    }

    public function result(SpeakingSubmission $submission, FeatureGate $featureGate)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('prompt');
        abort_if(!$submission->prompt || $submission->prompt->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            return redirect()
                ->route('mock.speaking.index')
                ->with('status', __('app.upgrade_required'));
        }

        $this->syncSubmissionAiStatus($submission);

        return view('mock.speaking.result', [
            'submission' => $submission,
        ]);
    }

    public function status(SpeakingSubmission $submission, FeatureGate $featureGate)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('prompt');
        abort_if(!$submission->prompt || $submission->prompt->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            abort(403);
        }

        $this->syncSubmissionAiStatus($submission);
        $payload = $submission->ai_feedback_json;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return response()->json([
            'status' => $submission->status,
            'band_score' => $submission->band_score,
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
    }

    private function applySpeakingFeedbackFromRequest(SpeakingSubmission $submission, \App\Models\AiRequest $aiRequest): void
    {
        $text = (string) ($aiRequest->output_json['text'] ?? '');
        $parsed = $this->extractJson($text);

        $submission->update([
            'status' => 'done',
            'band_score' => $parsed['overall_band'] ?? null,
            'ai_feedback' => $parsed['summary'] ?? null,
            'ai_feedback_json' => $parsed ?: null,
            'ai_error' => null,
        ]);
    }

    private function buildMockSpeakingFeedbackPrompt(SpeakingPrompt $prompt, string $responseText, bool $hasAudio): string
    {
        $taskType = 'speaking';
        $part = $prompt->part;
        $cefr = $prompt->difficulty ?: '';
        $hasAudioFlag = $hasAudio ? 'true' : 'false';
        $languageInstruction = $this->feedbackLanguageInstruction();

        return "You are a STRICT IELTS Examiner.\n"
            ."This is a MOCK TEST evaluation.\n"
            ."Evaluate ONLY this response.\n\n"
            ."CRITICAL OUTPUT RULES:\n"
            ."- Return ONLY a single VALID JSON object.\n"
            ."- No explanations, no markdown, no extra text.\n"
            ."- JSON must be complete and parsable.\n\n"
            ."SCORING RULE:\n"
            ."- Use strict IELTS band logic (0.0 to 9.0).\n"
            ."- If unsure between two bands, choose the LOWER band.\n"
            ."- Do not overgrade.\n\n"
            ."INPUT CONTEXT:\n"
            ."- task_type: {$taskType}\n"
            ."- mode: mock\n"
            ."- part: {$part}\n"
            ."- cefr_level: {$cefr}\n"
            ."- task_prompt: {$prompt->prompt}\n"
            ."- user_answer: {$responseText}\n"
            ."- has_audio: {$hasAudioFlag}\n\n"
            ."PRONUNCIATION RULE:\n"
            ."- If has_audio is false:\n"
            ."  pronunciation.band = null\n"
            ."  pronunciation.notes = \"Not assessed (text-only).\"\n\n"
            ."LANGUAGE RULE:\n"
            ."- {$languageInstruction}\n\n"
            ."OUTPUT JSON SCHEMA:\n"
            ."{\n"
            ."  \"overall_band\": 0.0,\n"
            ."  \"task_type\": \"{$taskType}\",\n"
            ."  \"cefr_level\": \"{$cefr}\",\n"
            ."  \"part\": {$part},\n"
            ."  \"criteria\": {\n"
            ."    \"task_response\": { \"band\": 0.0, \"notes\": \"\" },\n"
            ."    \"coherence_cohesion\": { \"band\": 0.0, \"notes\": \"\" },\n"
            ."    \"lexical_resource\": { \"band\": 0.0, \"notes\": \"\" },\n"
            ."    \"grammar_range_accuracy\": { \"band\": 0.0, \"notes\": \"\" },\n"
            ."    \"fluency_coherence\": { \"band\": 0.0, \"notes\": \"\" },\n"
            ."    \"pronunciation\": { \"band\": null, \"notes\": \"\" }\n"
            ."  },\n"
            ."  \"strengths\": [\"\", \"\"],\n"
            ."  \"weaknesses\": [\"\", \"\"],\n"
            ."  \"next_steps\": [\"\", \"\", \"\"],\n"
            ."  \"summary\": \"\"\n"
            ."}\n\n"
            ."FINAL CHECK:\n"
            ."- JSON must be valid.\n"
            ."- No text outside JSON.\n\n"
            ."Now generate the JSON evaluation.";
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

    private function canAccess($user, FeatureGate $featureGate): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->is_admin || $user->is_super_admin) {
            return true;
        }

        return $featureGate->userCan($user, 'mock_tests');
    }

    private function feedbackLanguageInstruction(): string
    {
        return match (app()->getLocale()) {
            'uz' => 'Write all feedback in Uzbek language.',
            'ru' => 'Write all feedback in Russian language.',
            default => 'Write all feedback in English language.',
        };
    }
}
