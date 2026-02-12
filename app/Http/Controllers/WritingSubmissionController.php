<?php

namespace App\Http\Controllers;

use App\Models\AiRequest;
use App\Models\WritingSubmission;
use App\Models\WritingTask;
use App\Exceptions\LimitExceededException;
use App\Services\FeatureGate;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WritingSubmissionController extends Controller
{
    public function store(
        Request $request,
        WritingTask $task,
        FeatureGate $featureGate,
        RateLimiterMySql $rateLimiter,
        AiRequestService $service
    )
    {
        abort_if(!$task->is_active || $task->mode !== 'practice', 404);

        $user = $request->user();
        $canWriting = $user && ($user->is_admin || $featureGate->userCan($user, 'writing_ai'));
        $isPreview = in_array($task->id, WritingTask::freePreviewIds('practice'), true);

        if (!$canWriting && !$isPreview) {
            return redirect()->route('writing.index')->with('status', __('app.upgrade_required'));
        }

        $data = $request->validate([
            'response_text' => ['required', 'string', 'min:30'],
        ], [
            'response_text.required' => __('app.writing_text_required'),
        ]);

        $responseText = trim((string) $data['response_text']);
        $wordCount = Str::wordCount($responseText);

        if ($task->min_words && $wordCount < $task->min_words) {
            return back()
                ->withErrors(['response_text' => __('app.writing_min_words', ['count' => $task->min_words])])
                ->withInput();
        }

        $submission = WritingSubmission::create([
            'user_id' => $request->user()->id,
            'writing_task_id' => $task->id,
            'response_text' => $responseText,
            'word_count' => $wordCount,
            'status' => 'queued',
            'submitted_at' => now(),
        ]);

        $practiceDriver = (string) config('ai.writing_practice_driver', 'local');
        if ($practiceDriver === 'local') {
            $payload = $this->buildLocalPracticeFeedback($task, $responseText, $wordCount);
            $submission->update([
                'status' => 'done',
                'band_score' => null,
                'ai_feedback' => (string) ($payload['summary'] ?? ''),
                'ai_feedback_json' => $payload,
                'ai_error' => null,
                'completed_at' => now(),
            ]);

            return redirect()->route('writing.submissions.show', $submission);
        }

        $prompt = $this->buildWritingFeedbackPrompt($submission);
        $context = [
            'submission_id' => $submission->id,
            'task_type' => (string) $task->task_type,
            'task_prompt' => (string) $task->prompt,
            'response_text' => $responseText,
        ];

        $allowed = $rateLimiter->hit('user', (string) $submission->user_id, (int) config('ai.user_rpm', 20));
        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$allowed || !$globalAllowed) {
            $message = !$allowed ? 'User rate limit exceeded.' : 'Global rate limit exceeded.';
            $aiRequest = $service->logFailure(
                $submission->user_id,
                'writing_feedback',
                $prompt,
                $context,
                ['temperature' => 0.2, 'max_output_tokens' => 1200],
                $message,
            );
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'ai_request_id' => $aiRequest->id,
                'completed_at' => now(),
            ]);

            return redirect()->route('writing.submissions.show', $submission)
                ->with('status', $message);
        }

        try {
            $aiRequest = $service->create(
                $submission->user_id,
                'writing_feedback',
                $prompt,
                $context,
                ['temperature' => 0.2, 'max_output_tokens' => 1200]
            );
        } catch (LimitExceededException $e) {
            $message = __('app.daily_limit_reached');
            $aiRequest = $service->logFailure(
                $submission->user_id,
                'writing_feedback',
                $prompt,
                $context,
                ['temperature' => 0.2, 'max_output_tokens' => 1200],
                $message,
            );
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'ai_request_id' => $aiRequest->id,
                'completed_at' => now(),
            ]);
            return redirect()->route('writing.submissions.show', $submission)
                ->with('status', $message)
                ->with('upgrade_prompt', true);
        }

        $submission->update([
            'ai_request_id' => $aiRequest->id,
            'status' => $aiRequest->status === 'done' ? 'done' : 'queued',
        ]);

        if ($aiRequest->status === 'done') {
            $this->applyWritingFeedbackFromRequest($submission, $aiRequest);
        }

        return redirect()->route('writing.submissions.show', $submission);
    }

    public function show(WritingSubmission $submission, FeatureGate $featureGate)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('task');
        abort_if(!$submission->task || $submission->task->mode !== 'practice', 404);

        $user = Auth::user();
        $canWriting = $user && ($user->is_admin || $featureGate->userCan($user, 'writing_ai'));
        $isPreview = in_array($submission->writing_task_id, WritingTask::freePreviewIds('practice'), true);
        if (!$canWriting && !$isPreview) {
            return view('writing.locked');
        }

        $submission->load('task');
        $this->syncSubmissionAiStatus($submission);
        $helps = \App\Models\WritingAIHelp::where('writing_submission_id', $submission->id)
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(8)
            ->get();
        $progressSnapshot = $this->buildPracticeProgressSnapshot($submission);
        $displayAccuracyPercent = $this->resolvePracticeAccuracy($submission);

        return view('writing.submission', [
            'submission' => $submission,
            'helps' => $helps,
            'progressSnapshot' => $progressSnapshot,
            'displayAccuracyPercent' => $displayAccuracyPercent,
        ]);
    }

    public function status(WritingSubmission $submission)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('task');
        abort_if(!$submission->task || $submission->task->mode !== 'practice', 404);

        $this->syncSubmissionAiStatus($submission);

        $payload = $submission->ai_feedback_json;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
        $accuracyPercent = $this->resolvePracticeAccuracy($submission);

        return response()->json([
            'status' => $submission->status,
            'band_score' => $submission->band_score,
            'accuracy_percent' => $accuracyPercent,
            'ai_feedback' => $submission->ai_feedback,
            'ai_feedback_json' => $payload,
            'diagnostic' => data_get($payload, 'diagnostic'),
            'error_map' => data_get($payload, 'error_map'),
            'health_index' => data_get($payload, 'health_index'),
            'ai_error' => $submission->ai_error,
        ]);
    }

    private function syncSubmissionAiStatus(WritingSubmission $submission): void
    {
        if (!$submission->ai_request_id) {
            return;
        }

        $aiRequest = AiRequest::find($submission->ai_request_id);
        if (!$aiRequest) {
            return;
        }

        if ($aiRequest->status === 'done' && $submission->status !== 'done') {
            $this->applyWritingFeedbackFromRequest($submission, $aiRequest);
        } elseif ($aiRequest->isStuckPending((int) config('ai.max_pending_seconds', 120))) {
            $aiRequest->update([
                'status' => 'failed',
                'error_text' => __('app.ai_help_failed'),
                'finished_at' => now(),
            ]);
            $submission->update([
                'status' => 'failed',
                'ai_error' => __('app.ai_help_failed'),
                'completed_at' => now(),
            ]);
        } elseif ($aiRequest->status === 'pending' && $aiRequest->isQuotaError()) {
            $aiRequest->update([
                'status' => 'failed_quota',
                'finished_at' => now(),
            ]);
            $submission->update([
                'status' => 'failed',
                'ai_error' => $aiRequest->error_text,
                'completed_at' => now(),
            ]);
        } elseif (in_array($aiRequest->status, ['failed', 'failed_quota'], true)) {
            $submission->update([
                'status' => 'failed',
                'ai_error' => $aiRequest->error_text,
                'completed_at' => now(),
            ]);
        } elseif ($aiRequest->status === 'processing' && $submission->status !== 'running') {
            $submission->update(['status' => 'running']);
        }

        if ($submission->status === 'done' && !$submission->ai_feedback_json) {
            $this->recoverFeedbackJsonIfPossible($submission);
        }

        if ($submission->status === 'done' && is_array($submission->ai_feedback_json)) {
            $normalized = $this->normalizePracticePayload(
                $submission->ai_feedback_json,
                (string) $submission->response_text,
                (int) ($submission->task?->min_words ?? 0),
                $submission->task
            );
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

    private function applyWritingFeedbackFromRequest(WritingSubmission $submission, AiRequest $aiRequest): void
    {
        $text = (string) ($aiRequest->output_json['text'] ?? '');
        $parsed = $this->extractJson($text) ?: $this->extractLoosePracticePayload($text);
        $taskPrompt = (string) ($submission->task?->prompt ?? '');
        $taskType = strtoupper((string) ($submission->task?->task_type ?? ''));

        if ($this->isInvalidWritingFeedback($parsed, $text, $taskPrompt, $taskType)) {
            if ($this->canRetryInvalidFeedback($submission)) {
                $this->retryWritingFeedback($submission, 'invalid_feedback');
                return;
            }
            if ($submission->task) {
                $parsed = $this->buildLocalPracticeFeedback(
                    $submission->task,
                    (string) $submission->response_text,
                    (int) ($submission->word_count ?? 0)
                );
            }
        }
        $parsed = $this->normalizePracticePayload(
            $parsed,
            (string) $submission->response_text,
            (int) ($submission->task?->min_words ?? 0),
            $submission->task
        );

        $submission->update([
            'status' => 'done',
            'band_score' => null,
            'ai_feedback' => $parsed['summary'] ?? ($text ?: null),
            'ai_feedback_json' => $parsed ?: null,
            'ai_error' => null,
            'completed_at' => now(),
        ]);
    }

    private function buildWritingFeedbackPrompt(WritingSubmission $submission): string
    {
        $task = $submission->task;
        $taskType = strtoupper((string) $task?->task_type);
        $cefrLevel = (string) ($task?->difficulty ?? '');
        $languageInstruction = $this->feedbackLanguageInstruction();
        $taskRules = $taskType === 'TASK1'
            ? "TASK 1 RULES:\n"
                ."- Do NOT ask for opinions, reasons, or consequences.\n"
                ."- Do NOT give advice (should/must/need to).\n"
                ."- Do NOT include a conclusion (\"in conclusion\", \"to conclude\").\n"
                ."- Focus ONLY on overview, trends, comparisons, and data language.\n"
                ."- Feedback must NOT encourage Task 2 style writing.\n\n"
            : "TASK 2 RULES:\n"
                ."- Require a clear position and explained ideas.\n"
                ."- Encourage reasons and specific examples.\n"
                ."- A brief conclusion is expected.\n"
                ."- Do NOT discuss charts/graphs/data descriptions.\n\n";

        return "You are IELTS EDU Writing PRACTICE evaluator.\n"
            ."This is NOT an exam simulation.\n"
            ."Your goal is to teach, not rank.\n\n"
            ."ABSOLUTE RULES:\n"
            ."- Do NOT assign IELTS band scores in practice mode.\n"
            ."- Do NOT use exam-style wording like real score or exam result.\n"
            ."- Focus on clear corrections and improvement steps.\n"
            ."- If writing is weak, say it clearly and concretely.\n\n"
            .$taskRules
            ."INPUT:\n"
            ."- module: writing\n"
            ."- mode: practice\n"
            ."- task_type: {$taskType}\n"
            ."- cefr_level: {$cefrLevel}\n"
            ."- prompt: {$task?->prompt}\n"
            ."- user_answer: {$submission->response_text}\n\n"
            ."OUTPUT RULES:\n"
            ."- Return ONLY one valid JSON object.\n"
            ."- Keep language simple and instructional.\n"
            ."- Do not include any numeric band.\n"
            ."- In each criteria notes, mention one concrete phrase from user answer in quotes AND give one specific fix.\n"
            ."- Use DIFFERENT quotes in each criteria note (no reuse).\n"
            ."- strengths and weaknesses should each include at least 2 short points.\n"
            ."- improvements must include exactly 3 actionable practice steps.\n\n"
            ."- Do NOT repeat the same sentence or idea across summary, criteria notes, strengths, weaknesses, improvements.\n"
            ."- Each strength/weakness must reference a different idea from the essay (no duplicates).\n\n"
            ."- Provide diagnostic percent (0-100) for 5 areas: task_response, coherence_cohesion, lexical_resource, grammar_accuracy, sentence_variety.\n"
            ."- Provide error_map counts for grammar, vocabulary, structure.\n"
            ."- Provide health_index with overall_percent and label (Strong/Developing/Weak foundation).\n"
            ."- Provide improvement_plan: immediate_fixes, short_term_focus, long_term_growth (2-4 items each).\n\n"
            ."- Provide text_errors list (4-8 items). Each item must include exact incorrect fragment from user_answer (before) and the corrected version (after).\n"
            ."- Do NOT invent mistakes. Only report errors that exist in the user's text.\n\n"
            ."- Do not repeat the same sentence across summary and criteria notes.\n"
            ."- Each improvement must say what to practice, how long, and what result to check.\n\n"
            ."LANGUAGE RULE:\n"
            ."- {$languageInstruction}\n\n"
            ."JSON SCHEMA:\n"
            ."{\n"
            ."  \"module\": \"writing\",\n"
            ."  \"mode\": \"practice\",\n"
            ."  \"task_type\": \"{$taskType}\",\n"
            ."  \"cefr_level\": \"{$cefrLevel}\",\n"
            ."  \"accuracy_percent\": 0,\n"
            ."  \"overall_band\": null,\n"
            ."  \"criteria\": {\n"
            ."    \"task_response\": {\"band\": null, \"notes\": \"\"},\n"
            ."    \"coherence_cohesion\": {\"band\": null, \"notes\": \"\"},\n"
            ."    \"lexical_resource\": {\"band\": null, \"notes\": \"\"},\n"
            ."    \"grammar_range_accuracy\": {\"band\": null, \"notes\": \"\"}\n"
            ."  },\n"
            ."  \"diagnostic\": {\n"
            ."    \"task_response\": {\"percent\": 0, \"notes\": [\"\"]},\n"
            ."    \"coherence_cohesion\": {\"percent\": 0, \"notes\": [\"\"]},\n"
            ."    \"lexical_resource\": {\"percent\": 0, \"notes\": [\"\"]},\n"
            ."    \"grammar_accuracy\": {\"percent\": 0, \"notes\": [\"\"]},\n"
            ."    \"sentence_variety\": {\"percent\": 0, \"notes\": [\"\"]}\n"
            ."  },\n"
            ."  \"error_map\": {\n"
            ."    \"grammar\": {\"articles\": 0, \"tenses\": 0, \"prepositions\": 0, \"run_on_sentences\": 0, \"subject_verb_agreement\": 0},\n"
            ."    \"vocabulary\": {\"repetition\": 0, \"wrong_collocation\": 0, \"simple_word_overuse\": 0},\n"
            ."    \"structure\": {\"weak_intro\": false, \"missing_example\": false, \"weak_conclusion\": false, \"unclear_position\": false, \"off_topic_paragraphs\": 0}\n"
            ."  },\n"
            ."  \"improvement_plan\": {\n"
            ."    \"immediate_fixes\": [\"\"],\n"
            ."    \"short_term_focus\": [\"\"],\n"
            ."    \"long_term_growth\": [\"\"]\n"
            ."  },\n"
            ."  \"health_index\": {\"overall_percent\": 0, \"label\": \"\"},\n"
            ."  \"strengths\": [\"\"],\n"
            ."  \"weaknesses\": [\"\"],\n"
            ."  \"improvements\": [\"\", \"\", \"\"],\n"
            ."  \"corrections\": [\n"
            ."    {\"issue\": \"\", \"before\": \"\", \"after\": \"\"}\n"
            ."  ],\n"
            ."  \"text_errors\": [\n"
            ."    {\"before\": \"\", \"after\": \"\", \"reason\": \"\"}\n"
            ."  ],\n"
            ."  \"examples\": [\"\"],\n"
            ."  \"summary\": \"\",\n"
            ."  \"upgrade_hint\": \"\"\n"
            ."}\n\n"
            ."FINAL CHECK:\n"
            ."- Valid JSON only.\n"
            ."- No band score anywhere.\n"
            ."- No extra text outside JSON.";
    }

    private function isInvalidWritingFeedback(?array $parsed, string $text, string $taskPrompt, string $taskType = ''): bool
    {
        $feedback = strtolower((string) $text);
        $prompt = strtolower((string) $taskPrompt);
        $taskType = strtoupper((string) $taskType);

        $forbiddenKeywords = ['chart', 'graph', 'table', 'bar chart', 'line graph', 'pie chart', 'transport'];
        $promptMentionsData = false;
        foreach ($forbiddenKeywords as $keyword) {
            if (str_contains($prompt, $keyword)) {
                $promptMentionsData = true;
                break;
            }
        }

        $isTask1 = $taskType === 'TASK1' || $promptMentionsData;
        if ($isTask1) {
            $task1Forbidden = [
                'analysis',
                'analytical depth',
                'reasons',
                'consequences',
                'in my opinion',
                'i think',
                'i believe',
                'personally',
                'should',
                'must',
                'need to',
                'have to',
                'advice',
                'to conclude',
                'in conclusion',
                'to sum up',
            ];

            foreach ($task1Forbidden as $phrase) {
                if ($phrase !== '' && str_contains($feedback, $phrase)) {
                    return true;
                }
            }

            if (is_array($parsed)) {
                $summary = strtolower((string) ($parsed['summary'] ?? ''));
                foreach ($task1Forbidden as $phrase) {
                    if ($summary && str_contains($summary, $phrase)) {
                        return true;
                    }
                }

                $notes = collect($parsed['criteria'] ?? [])
                    ->pluck('notes')
                    ->map(fn ($value) => strtolower((string) $value))
                    ->filter();
                foreach ($notes as $note) {
                    foreach ($task1Forbidden as $phrase) {
                        if ($phrase !== '' && str_contains($note, $phrase)) {
                            return true;
                        }
                    }
                }

                $lists = collect([
                    ...($parsed['strengths'] ?? []),
                    ...($parsed['weaknesses'] ?? []),
                    ...($parsed['improvements'] ?? []),
                    ...($parsed['examples'] ?? []),
                ])->map(fn ($value) => strtolower((string) $value))
                    ->filter();
                foreach ($lists as $item) {
                    foreach ($task1Forbidden as $phrase) {
                        if ($phrase !== '' && str_contains($item, $phrase)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        foreach ($forbiddenKeywords as $keyword) {
            if (str_contains($feedback, $keyword)) {
                return true;
            }
        }

        if (is_array($parsed)) {
            $summary = strtolower((string) ($parsed['summary'] ?? ''));
            foreach ($forbiddenKeywords as $keyword) {
                if ($summary && str_contains($summary, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function canRetryInvalidFeedback(WritingSubmission $submission): bool
    {
        $key = 'writing:invalid_feedback_retry:'.$submission->id;
        $count = (int) Cache::get($key, 0);
        if ($count >= 1) {
            return false;
        }
        Cache::put($key, $count + 1, now()->addMinutes(30));
        return true;
    }

    private function retryWritingFeedback(WritingSubmission $submission, string $reason): void
    {
        $prompt = $this->buildWritingFeedbackPrompt($submission);
        $context = [
            'submission_id' => $submission->id,
            'task_type' => (string) ($submission->task?->task_type),
            'task_prompt' => (string) ($submission->task?->prompt),
            'response_text' => (string) $submission->response_text,
            'retry_reason' => $reason,
        ];

        try {
            $aiRequest = app(AiRequestService::class)->create(
                $submission->user_id,
                'writing_feedback',
                $prompt,
                $context,
                ['temperature' => 0.2, 'max_output_tokens' => 1200],
                'writing_feedback:'.$submission->id.':retry:'.$reason
            );
        } catch (LimitExceededException $e) {
            $submission->update([
                'status' => 'failed',
                'ai_error' => __('app.daily_limit_reached'),
                'completed_at' => now(),
            ]);
            return;
        }

        $submission->update([
            'status' => $aiRequest->status === 'done' ? 'done' : 'queued',
            'ai_request_id' => $aiRequest->id,
            'ai_error' => null,
            'completed_at' => null,
        ]);

        if ($aiRequest->status === 'done') {
            $this->applyWritingFeedbackFromRequest($submission, $aiRequest);
        }
    }

    private function extractJson(string $content): ?array
    {
        $candidates = $this->buildJsonCandidates($content);
        foreach ($candidates as $candidate) {
            $decoded = $this->decodeJsonCandidate($candidate);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function buildJsonCandidates(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        $candidates = [$trimmed];
        $fenceStripped = preg_replace('/^```(?:json)?\s*/i', '', $trimmed);
        $fenceStripped = preg_replace('/\s*```$/', '', $fenceStripped);
        $fenceStripped = trim((string) $fenceStripped);
        if ($fenceStripped !== '' && $fenceStripped !== $trimmed) {
            $candidates[] = $fenceStripped;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($trimmed, $start, $end - $start + 1);
        }

        if (
            (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"'))
            || (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
        ) {
            $candidates[] = substr($trimmed, 1, -1);
        }

        return array_values(array_unique($candidates));
    }

    private function decodeJsonCandidate(string $candidate): ?array
    {
        $normalized = $this->normalizeJsonCandidate($candidate);
        if ($normalized === '') {
            return null;
        }

        $decoded = json_decode($normalized, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $unescaped = stripslashes($normalized);
        if ($unescaped !== $normalized) {
            $decoded = json_decode($unescaped, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeJsonCandidate(string $candidate): string
    {
        $normalized = trim($candidate);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized);
        $normalized = str_replace(
            ["\u{201C}", "\u{201D}", "\u{201E}", "\u{00AB}", "\u{00BB}"],
            '"',
            $normalized
        );
        $normalized = str_replace(["\u{2018}", "\u{2019}"], "'", $normalized);
        $normalized = preg_replace('/,\s*([}\]])/', '$1', $normalized);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $normalized);

        return trim((string) $normalized);
    }

    private function recoverFeedbackJsonIfPossible(WritingSubmission $submission): void
    {
        $raw = trim((string) $submission->ai_feedback);
        if ($raw === '') {
            return;
        }

        $parsed = $this->extractJson($raw) ?: $this->extractLoosePracticePayload($raw);
        if (!$parsed) {
            return;
        }
        $parsed = $this->normalizePracticePayload(
            $parsed,
            (string) $submission->response_text,
            (int) ($submission->task?->min_words ?? 0),
            $submission->task
        );

        $submission->update([
            'ai_feedback' => $parsed['summary'] ?? ($raw ?: null),
            'ai_feedback_json' => $parsed,
            'band_score' => null,
        ]);
    }

    private function normalizePracticePayload(?array $parsed, string $responseText = '', int $minWords = 0, ?WritingTask $task = null): ?array
    {
        if (!is_array($parsed)) {
            return $parsed;
        }

        if (str_starts_with((string) ($parsed['engine'] ?? ''), 'local_practice_v')) {
            $parsed['module'] = 'writing';
            $parsed['mode'] = 'practice';
            $parsed['overall_band'] = null;
            $parsed['accuracy_percent'] = max(0, min(100, (int) round((float) ($parsed['accuracy_percent'] ?? 0))));
            foreach (['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy'] as $key) {
                if (!isset($parsed['criteria'][$key]) || !is_array($parsed['criteria'][$key])) {
                    $parsed['criteria'][$key] = ['band' => null, 'notes' => ''];
                } else {
                    $parsed['criteria'][$key]['band'] = null;
                    $parsed['criteria'][$key]['notes'] = $this->cleanAiText((string) ($parsed['criteria'][$key]['notes'] ?? ''));
                }
            }
            foreach (['strengths', 'weaknesses', 'improvements', 'examples'] as $listKey) {
                $items = is_array($parsed[$listKey] ?? null) ? $parsed[$listKey] : [];
                $parsed[$listKey] = $this->sanitizeStringList($items);
            }
            $parsed['text_errors'] = $this->sanitizeTextErrors($parsed['text_errors'] ?? $parsed['corrections'] ?? []);
            if (empty($parsed['text_errors']) && $responseText !== '') {
                $parsed['text_errors'] = $this->buildTextErrorsFallback($responseText);
            }
            return $parsed;
        }

        $analysis = $this->analyzeWritingResponse($responseText, $minWords);
        $estimatedAccuracy = $analysis['accuracy'];
        $accuracy = $parsed['accuracy_percent'] ?? null;
        if (!is_numeric($accuracy)) {
            $legacyBand = $parsed['overall_band'] ?? null;
            if (is_numeric($legacyBand)) {
                $accuracy = ((float) $legacyBand / 9) * 100;
            } else {
                $accuracy = $estimatedAccuracy;
            }
        }

        $accuracy = is_numeric($accuracy) && (int) round((float) $accuracy) > 0
            ? max(0, min(100, (int) round((float) $accuracy)))
            : $estimatedAccuracy;

        $parsed['accuracy_percent'] = $accuracy;
        $parsed['overall_band'] = null;
        $parsed['module'] = 'writing';
        $parsed['mode'] = 'practice';
        $parsed['summary'] = $this->cleanAiText((string) ($parsed['summary'] ?? ''));

        foreach (['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy'] as $key) {
            if (!isset($parsed['criteria'][$key]) || !is_array($parsed['criteria'][$key])) {
                $parsed['criteria'][$key] = ['band' => null, 'notes' => ''];
                continue;
            }

            $parsed['criteria'][$key]['band'] = null;
            $parsed['criteria'][$key]['notes'] = $this->cleanAiText((string) ($parsed['criteria'][$key]['notes'] ?? ''));
        }

        if ($this->needsCriteriaFallback($parsed['criteria'] ?? [])) {
            $parsed['criteria'] = $this->buildFallbackCriteriaNotes($responseText, $minWords, $parsed['criteria'] ?? []);
        }
        $parsed['criteria'] = $this->replaceWeakCriteriaNotes($parsed['criteria'] ?? [], $responseText, $minWords);

        foreach (['strengths', 'weaknesses', 'improvements'] as $listKey) {
            $items = $parsed[$listKey] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            $parsed[$listKey] = $this->sanitizeStringList($items);
        }
        $parsed['text_errors'] = $this->sanitizeTextErrors($parsed['text_errors'] ?? $parsed['corrections'] ?? []);
        if (empty($parsed['text_errors']) && $responseText !== '') {
            $parsed['text_errors'] = $this->buildTextErrorsFallback($responseText);
        }

        $parsed = $this->applyFeedbackDiversity($parsed, $responseText, $minWords);

        if ($parsed['summary'] === '') {
            $parsed['summary'] = collect($parsed['criteria'] ?? [])
                ->pluck('notes')
                ->filter()
                ->take(2)
                ->implode(' ');
        }

        if ($this->isShallowFeedbackPayload($parsed)) {
            $parsed = $this->applyHeuristicPracticeFeedback($parsed, $responseText, $minWords, $analysis);
        }

        if ($task && $responseText !== '') {
            $parsed = $this->mergeLocalPracticeDiagnostics($parsed, $task, $responseText);
            $parsed['task_type'] = strtoupper((string) ($task->task_type ?? ''));
            $parsed = $this->applyTaskCaps($parsed, $task, $responseText);
            $parsed = $this->enforcePracticeScoreConsistency($parsed);
        }

        $parsed['checklist'] = $this->buildPracticeChecklist($parsed);

        return $parsed;
    }

    private function replaceWeakCriteriaNotes(array $criteria, string $responseText, int $minWords): array
    {
        if (empty($criteria)) {
            return $criteria;
        }

        $fallback = $this->buildFallbackCriteriaNotes($responseText, $minWords, $criteria);
        $keys = ['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy'];

        foreach ($keys as $key) {
            $note = $this->cleanAiText((string) data_get($criteria, $key.'.notes', ''));
            $needsReplace = $note === ''
                || strlen($note) < 35
                || $this->isGenericFeedbackText($note)
                || $this->isLocaleMismatchText($note)
                || preg_match('/[\\(,:;]\\s*$/', $note) === 1;

            if ($needsReplace && isset($fallback[$key]['notes'])) {
                $criteria[$key]['notes'] = $fallback[$key]['notes'];
            }
        }

        return $criteria;
    }

    private function buildPracticeChecklist(array $parsed): array
    {
        $items = [];
        $errorMap = is_array($parsed['error_map'] ?? null) ? $parsed['error_map'] : [];
        $grammar = is_array($errorMap['grammar'] ?? null) ? $errorMap['grammar'] : [];
        $vocab = is_array($errorMap['vocabulary'] ?? null) ? $errorMap['vocabulary'] : [];
        $structure = is_array($errorMap['structure'] ?? null) ? $errorMap['structure'] : [];
        $taskType = strtoupper((string) ($parsed['task_type'] ?? ''));
        $isTask1 = $taskType === 'TASK1';
        $minParagraphs = $isTask1 ? 3 : 4;

        if (($structure['nonsense_detected'] ?? false)) {
            $examples = is_array($structure['nonsense_words'] ?? null) ? $structure['nonsense_words'] : [];
            $sample = !empty($examples) ? implode(', ', array_slice($examples, 0, 3)) : '';
            $suffix = $sample !== '' ? " Misol: {$sample}." : '';
            $items[] = $this->localizedText(
                "Nonsense/garbage so'zlar topildi.{$suffix}",
                "Nonsense/garbage slova najdeny.{$suffix}",
                "Nonsense/garbage tokens detected.{$suffix}"
            );
        }

        if (!$isTask1 && ($structure['unclear_position'] ?? false)) {
            $items[] = $this->localizedText(
                "Introda aniq pozitsiya yozing (I agree/disagree).",
                "Vvedenii napishete chetkuyu poziciyu (I agree/disagree).",
                "Write a clear position in the introduction (I agree/disagree)."
            );
        }
        if (!$isTask1 && ($structure['missing_example'] ?? false)) {
            $items[] = $this->localizedText(
                "Har paragrafga bitta real misol qo'shing.",
                "Dobav'te po odnomu real'nomu primeru v kazhdyi paragraf.",
                "Add one real example in each paragraph."
            );
        }
        if (($structure['missing_paragraphs'] ?? false)) {
            $items[] = $this->localizedText(
                "Matnni kamida {$minParagraphs} paragrafga ajrating.",
                "Razdelite tekst minimum na {$minParagraphs} paragrafov.",
                "Split the essay into at least {$minParagraphs} paragraphs."
            );
        }
        if (($structure['low_word_count'] ?? false)) {
            $items[] = $this->localizedText(
                "So'z sonini minimal talabga yetkazing.",
                "Dovodite kolichestvo slov do minimal'nogo trebovaniya.",
                "Bring the word count up to the minimum requirement."
            );
        }
        if ($isTask1 && ($structure['missing_overview'] ?? false)) {
            $items[] = $this->localizedText(
                "Overview yozing: umumiy trendni 1-2 gapda ayting.",
                "Sdelайте обзор: obshchii trend v 1-2 predlozheniyah.",
                "Write an overview: state the overall trend in 1-2 sentences."
            );
        }
        if ($isTask1 && ($structure['missing_data'] ?? false)) {
            $items[] = $this->localizedText(
                "Kamida 2 ta raqam/foiz keltiring.",
                "Ukazhite minimum 2 chisla/процента.",
                "Include at least 2 specific numbers/percentages."
            );
        }
        if ($isTask1 && ($structure['missing_comparison'] ?? false)) {
            $items[] = $this->localizedText(
                "Taqqos bering: higher/lower yoki increase/decrease ishlating.",
                "Dobav'te sravneniya: higher/lower ili increase/decrease.",
                "Add comparisons using higher/lower or increase/decrease."
            );
        }
        if ($isTask1 && ($structure['missing_years'] ?? false)) {
            $items[] = $this->localizedText(
                "Yillarni aniq yozing (masalan, 2000 va 2020).",
                "Явно укажите годы (например, 2000 и 2020).",
                "Explicitly mention the years (e.g., 2000 and 2020)."
            );
        }
        if ($isTask1 && ($structure['has_opinion'] ?? false)) {
            $items[] = $this->localizedText(
                "Shaxsiy fikr yozmang — faqat grafik ma'lumotini tasvirlang.",
                "Ne pишите lichnoe mnenie — tol'ko opisanie dannyh grafikа.",
                "Do not add personal opinion — describe the chart data only."
            );
        }
        if ($isTask1 && ($structure['task1_advice'] ?? false)) {
            $items[] = $this->localizedText(
                "Maslahat (should/must) yozmang; faqat ma'lumotni tasvirlang.",
                "Ne dayte sovetov (should/must); tol'ko opisanie dannyh.",
                "Do not give advice (should/must); describe the data only."
            );
        }
        if ($isTask1 && ($structure['task1_conclusion'] ?? false)) {
            $items[] = $this->localizedText(
                "Task 1da xulosa (in conclusion/to conclude) kerak emas.",
                "V Task 1 ne nuzhno zaklyuchenie (in conclusion/to conclude).",
                "Task 1 does not need a conclusion (in conclusion/to conclude)."
            );
        }
        if (($structure['weak_intro'] ?? false)) {
            $items[] = $this->localizedText(
                "Kirishni 2-3 gapda aniqroq yozing.",
                "Sdelayte vvedenie bolee chetkim v 2-3 predlozheniyah.",
                "Make the introduction clearer in 2-3 sentences."
            );
        }
        if (($structure['weak_conclusion'] ?? false)) {
            $items[] = $this->localizedText(
                "Xulosada pozitsiyani 1 gapda qayta tasdiqlang.",
                "V zaklyuchenii povtorite poziciyu v odnom predlozhenii.",
                "Restate your position in one clear conclusion sentence."
            );
        }
        if ((int) ($structure['off_topic_paragraphs'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Mavzudan chetga chiqadigan paragraf(lar)ni olib tashlang.",
                "Ub'erite paragrafy, kotorye uhodят ot temy.",
                "Remove paragraphs that go off-topic."
            );
        }

        if ((int) ($grammar['subject_verb_agreement'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Subject-verb agreement xatolarini tekshiring.",
                "Proverte soglasovanie podlezhaschego i skazuemogo.",
                "Check subject–verb agreement."
            );
        }
        if ((int) ($grammar['tenses'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Fe'l zamonlarini bir xilda ishlating.",
                "Sledite za edinym vremenem glagolov.",
                "Keep verb tenses consistent."
            );
        }
        if ((int) ($grammar['articles'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "a/an/the ishlatilishini tekshiring.",
                "Proverte ispol'zovanie a/an/the.",
                "Check a/an/the usage."
            );
        }
        if ((int) ($grammar['prepositions'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Preposition xatolarini tuzating.",
                "Isprav'te oshibki s predlogami.",
                "Fix preposition errors."
            );
        }
        if ((int) ($grammar['run_on_sentences'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Uzun gaplarni 2 ta qisqa gapga bo'ling.",
                "Razdelite ochen' dlinye predlozheniya na 2.",
                "Split very long sentences into two."
            );
        }

        if ((int) ($vocab['repetition'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Takror so'zlarni sinonimlar bilan almashtiring.",
                "Zamenite povtory sinonimami.",
                "Replace repeated words with synonyms."
            );
        }
        if ((int) ($vocab['wrong_collocation'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Collocation xatolarini tuzating (make a decision kabi).",
                "Isprav'te kollokacii (naprimer, make a decision).",
                "Fix collocations (e.g., make a decision)."
            );
        }
        if ((int) ($vocab['simple_word_overuse'] ?? 0) > 0) {
            $items[] = $this->localizedText(
                "Juda sodda so'zlarni kamaytiring (very, good, bad).",
                "Umen'shite prostye slova (very, good, bad).",
                "Reduce very simple words (very, good, bad)."
            );
        }

        $items = $this->sanitizeStringList($items);
        if (count($items) < 3) {
            $extras = $this->buildPracticeFallbackLists('', 0, [])['improvements'] ?? [];
            $items = $this->mergeFeedbackLists($items, $extras, $items, 6);
        }

        return array_slice($items, 0, 6);
    }

    private function applyFeedbackDiversity(array $parsed, string $responseText, int $minWords): array
    {
        $criteriaNotes = collect($parsed['criteria'] ?? [])
            ->pluck('notes')
            ->map(fn ($value) => $this->cleanAiText((string) $value))
            ->filter()
            ->values();

        $bannedKeys = $this->collectFeedbackKeys($criteriaNotes->all());
        $summaryKey = $this->normalizeFeedbackKey((string) ($parsed['summary'] ?? ''));
        if ($summaryKey !== '') {
            $bannedKeys[] = $summaryKey;
        }

        $parsed['strengths'] = $this->dedupeFeedbackList($parsed['strengths'] ?? [], $bannedKeys, 4);
        $bannedKeys = array_values(array_unique(array_merge($bannedKeys, $this->collectFeedbackKeys($parsed['strengths']))));

        $parsed['weaknesses'] = $this->dedupeFeedbackList($parsed['weaknesses'] ?? [], $bannedKeys, 4);
        $bannedKeys = array_values(array_unique(array_merge($bannedKeys, $this->collectFeedbackKeys($parsed['weaknesses']))));

        $parsed['improvements'] = $this->dedupeFeedbackList($parsed['improvements'] ?? [], $bannedKeys, 3);

        $fallback = $this->buildPracticeFallbackLists($responseText, $minWords, $parsed['criteria'] ?? []);

        if (count($parsed['strengths']) < 2) {
            $parsed['strengths'] = $this->mergeFeedbackLists($parsed['strengths'], $fallback['strengths'] ?? [], $bannedKeys, 4);
        }
        $bannedKeys = array_values(array_unique(array_merge($bannedKeys, $this->collectFeedbackKeys($parsed['strengths']))));

        if (count($parsed['weaknesses']) < 2) {
            $parsed['weaknesses'] = $this->mergeFeedbackLists($parsed['weaknesses'], $fallback['weaknesses'] ?? [], $bannedKeys, 4);
        }
        $bannedKeys = array_values(array_unique(array_merge($bannedKeys, $this->collectFeedbackKeys($parsed['weaknesses']))));

        if (count($parsed['improvements']) < 3) {
            $parsed['improvements'] = $this->mergeFeedbackLists($parsed['improvements'], $fallback['improvements'] ?? [], $bannedKeys, 3);
        }

        $parsed['strengths'] = array_slice($parsed['strengths'], 0, 4);
        $parsed['weaknesses'] = array_slice($parsed['weaknesses'], 0, 4);
        $parsed['improvements'] = array_slice($parsed['improvements'], 0, 3);

        return $parsed;
    }

    private function mergeLocalPracticeDiagnostics(array $parsed, WritingTask $task, string $responseText): array
    {
        $wordCount = Str::wordCount($responseText);
        $local = $this->buildLocalPracticeFeedback($task, $responseText, $wordCount);

        if (empty($local)) {
            return $parsed;
        }

        $parsed['diagnostic'] = $this->mergeDiagnosticBlocks(
            (array) ($parsed['diagnostic'] ?? []),
            (array) ($local['diagnostic'] ?? [])
        );

        if (empty($parsed['error_map']) || !is_array($parsed['error_map'])) {
            $parsed['error_map'] = $local['error_map'] ?? [];
        }

        if (!$this->hasImprovementPlan($parsed['improvement_plan'] ?? [])) {
            $parsed['improvement_plan'] = $local['improvement_plan'] ?? [];
        }

        if (empty($parsed['health_index']) || !is_array($parsed['health_index'])) {
            $parsed['health_index'] = $local['health_index'] ?? [];
        }

        $accuracy = $parsed['accuracy_percent'] ?? null;
        if (!is_numeric($accuracy) || (int) round((float) $accuracy) <= 0) {
            $parsed['accuracy_percent'] = $local['accuracy_percent'] ?? $accuracy;
        }

        if (empty($parsed['text_errors']) && !empty($local['text_errors'])) {
            $parsed['text_errors'] = $local['text_errors'];
        }

        return $parsed;
    }

    private function mergeDiagnosticBlocks(array $aiDiagnostic, array $localDiagnostic): array
    {
        $keys = ['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_accuracy', 'sentence_variety'];
        $merged = [];

        foreach ($keys as $key) {
            $localBlock = is_array($localDiagnostic[$key] ?? null) ? $localDiagnostic[$key] : [];
            $aiBlock = is_array($aiDiagnostic[$key] ?? null) ? $aiDiagnostic[$key] : [];
            $notes = $aiBlock['notes'] ?? null;
            if (is_string($notes)) {
                $notes = [$notes];
            }
            if (!is_array($notes) || empty(array_filter($notes, fn ($note) => trim((string) $note) !== ''))) {
                $notes = $localBlock['notes'] ?? [];
            }

            $percent = $localBlock['percent'] ?? ($aiBlock['percent'] ?? 0);
            $merged[$key] = [
                'percent' => is_numeric($percent) ? (int) max(0, min(100, (int) round((float) $percent))) : 0,
                'notes' => $this->sanitizeStringList(is_array($notes) ? $notes : []),
            ];
        }

        return $merged;
    }

    private function hasImprovementPlan($plan): bool
    {
        if (!is_array($plan)) {
            return false;
        }

        $immediate = (array) ($plan['immediate_fixes'] ?? []);
        $shortTerm = (array) ($plan['short_term_focus'] ?? []);
        $longTerm = (array) ($plan['long_term_growth'] ?? []);

        return !empty(array_filter($immediate)) || !empty(array_filter($shortTerm)) || !empty(array_filter($longTerm));
    }

    private function extractLoosePracticePayload(string $text): ?array
    {
        $raw = trim($text);
        if ($raw === '') {
            return null;
        }

        $summary = $this->extractFirstQuotedValue($raw, 'summary');
        $criteria = $this->extractCriteriaNotesFromText($raw);
        $strengths = $this->extractSimpleArray($raw, 'strengths');
        $weaknesses = $this->extractSimpleArray($raw, 'weaknesses');
        $improvements = $this->extractSimpleArray($raw, 'improvements');
        if (empty($improvements)) {
            $improvements = $this->extractSimpleArray($raw, 'next_steps');
        }
        $hasCriteriaNotes = collect($criteria)
            ->pluck('notes')
            ->contains(fn ($note) => trim((string) $note) !== '');

        $accuracy = $this->extractNumberValue($raw, 'accuracy_percent');
        if (!is_numeric($accuracy)) {
            $legacyBand = $this->extractNumberValue($raw, 'overall_band');
            if (is_numeric($legacyBand)) {
                $accuracy = ((float) $legacyBand / 9) * 100;
            }
        }
        $accuracy = is_numeric($accuracy) ? (int) round((float) $accuracy) : null;

        if (empty($summary) && !$hasCriteriaNotes && empty($strengths) && empty($weaknesses) && empty($improvements)) {
            return null;
        }

        if (empty($summary) && $hasCriteriaNotes) {
            $summary = collect($criteria)
                ->pluck('notes')
                ->filter()
                ->take(2)
                ->implode(' ');
        }

        return [
            'module' => 'writing',
            'mode' => 'practice',
            'accuracy_percent' => $accuracy,
            'overall_band' => null,
            'criteria' => $criteria,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'improvements' => $improvements,
            'summary' => $summary ?: '',
        ];
    }

    private function extractCriteriaNotesFromText(string $text): array
    {
        $criteria = [];
        $pattern = '/"(task_response|coherence_cohesion|lexical_resource|grammar_range_accuracy|criteria)"\s*:\s*\{[^}]*"notes"\s*:\s*"([^"]*)"/i';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower((string) ($match[1] ?? ''));
                $note = trim(str_replace(['\\n', '\\"'], [' ', '"'], (string) ($match[2] ?? '')));
                if ($key === 'criteria') {
                    $key = 'task_response';
                }
                $criteria[$key] = [
                    'band' => null,
                    'notes' => $note,
                ];
            }
        }

        foreach (['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy'] as $key) {
            if (!isset($criteria[$key])) {
                $criteria[$key] = ['band' => null, 'notes' => ''];
            }
        }

        return $criteria;
    }

    private function extractSimpleArray(string $text, string $key): array
    {
        $pattern = '/"'.preg_quote($key, '/').'"\s*:\s*\[(.*?)\]/is';
        if (!preg_match($pattern, $text, $match)) {
            return [];
        }

        $body = (string) ($match[1] ?? '');
        if ($body === '') {
            return [];
        }

        $items = [];
        if (preg_match_all('/"([^"]*)"/', $body, $itemMatches)) {
            foreach ($itemMatches[1] as $item) {
                $clean = trim(str_replace(['\\n', '\\"'], [' ', '"'], (string) $item));
                if ($clean !== '') {
                    $items[] = $clean;
                }
            }
        }

        return array_values(array_unique($items));
    }

    private function extractFirstQuotedValue(string $text, string $key): ?string
    {
        $pattern = '/"'.preg_quote($key, '/').'"\s*:\s*"([^"]*)"/i';
        if (!preg_match($pattern, $text, $match)) {
            return null;
        }

        $value = trim(str_replace(['\\n', '\\"'], [' ', '"'], (string) ($match[1] ?? '')));
        return $value !== '' ? $value : null;
    }

    private function extractNumberValue(string $text, string $key): ?float
    {
        $pattern = '/"'.preg_quote($key, '/').'"\s*:\s*([0-9]+(?:\.[0-9]+)?)/i';
        if (!preg_match($pattern, $text, $match)) {
            return null;
        }

        $value = (float) ($match[1] ?? 0);
        return is_finite($value) ? $value : null;
    }

    private function cleanAiText(string $text): string
    {
        $clean = str_replace(['\\n', '\\"'], [' ', '"'], $text);
        $clean = preg_replace('/\s+/', ' ', $clean ?? '');
        $clean = trim((string) $clean);
        $clean = rtrim($clean, "\\");
        return trim($clean);
    }

    private function sanitizeStringList(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            $value = $this->cleanAiText((string) $item);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return array_values(array_unique($clean));
    }

    private function normalizeFeedbackKey(string $text): string
    {
        $value = Str::lower($this->cleanAiText($text));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value ?? '');
        return trim((string) $value);
    }

    private function areSimilarFeedbackKeys(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $short = strlen($a) <= strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;
        if (strlen($short) >= 30 && str_contains($long, $short)) {
            return true;
        }

        return false;
    }

    private function collectFeedbackKeys(array $items): array
    {
        $keys = [];
        foreach ($items as $item) {
            $key = $this->normalizeFeedbackKey((string) $item);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    private function dedupeFeedbackList(array $items, array $bannedKeys = [], int $limit = 0): array
    {
        $unique = [];
        $uniqueKeys = [];
        $normalizedBanned = array_values(array_filter(array_unique(array_map(
            fn ($value) => $this->normalizeFeedbackKey((string) $value),
            $bannedKeys
        ))));

        foreach ($items as $item) {
            $value = $this->cleanAiText((string) $item);
            if ($value === '') {
                continue;
            }

            $key = $this->normalizeFeedbackKey($value);
            if ($key === '') {
                continue;
            }

            $isBanned = false;
            foreach ($normalizedBanned as $banned) {
                if ($this->areSimilarFeedbackKeys($key, $banned)) {
                    $isBanned = true;
                    break;
                }
            }
            if ($isBanned) {
                continue;
            }

            $duplicate = false;
            foreach ($uniqueKeys as $existing) {
                if ($this->areSimilarFeedbackKeys($key, $existing)) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }

            $unique[] = $value;
            $uniqueKeys[] = $key;
        }

        if ($limit > 0) {
            $unique = array_slice($unique, 0, $limit);
        }

        return $unique;
    }

    private function mergeFeedbackLists(array $items, array $extras, array $bannedKeys, int $limit): array
    {
        $merged = $items;
        $currentKeys = $this->collectFeedbackKeys($merged);
        $ban = array_values(array_unique(array_merge($bannedKeys, $currentKeys)));
        $extras = $this->dedupeFeedbackList($extras, $ban);
        $merged = array_merge($merged, $extras);

        if ($limit > 0) {
            $merged = array_slice($merged, 0, $limit);
        }

        return $merged;
    }

    private function sanitizeTextErrors(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $value = $this->cleanAiText($item);
                if ($value !== '') {
                    $clean[] = ['before' => $value, 'after' => '', 'reason' => ''];
                }
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $before = $this->cleanAiText((string) ($item['before'] ?? $item['error'] ?? $item['issue'] ?? ''));
            $after = $this->cleanAiText((string) ($item['after'] ?? $item['fix'] ?? ''));
            $reason = $this->cleanAiText((string) ($item['reason'] ?? $item['note'] ?? $item['issue'] ?? ''));

            if ($before === '' && $after === '' && $reason === '') {
                continue;
            }

            $clean[] = [
                'before' => $before,
                'after' => $after,
                'reason' => $reason,
            ];
        }

        return array_values(array_slice($clean, 0, 8));
    }

    private function buildTextErrorsFallback(string $responseText): array
    {
        $corrections = $this->detectGrammarIssues($responseText);
        if (empty($corrections)) {
            return [];
        }

        $mapped = array_map(static function (array $item) {
            return [
                'before' => (string) ($item['before'] ?? ''),
                'after' => (string) ($item['after'] ?? ''),
                'reason' => (string) ($item['issue'] ?? ''),
            ];
        }, $corrections);

        return array_values(array_slice($mapped, 0, 8));
    }

    private function analyzeWritingResponse(string $responseText, int $minWords = 0): array
    {
        $text = trim($responseText);
        if ($text === '') {
            return [
                'accuracy' => 0,
                'word_count' => 0,
                'target_words' => $minWords > 0 ? $minWords : 180,
                'unique_ratio' => 0.0,
                'marker_count' => 0,
                'grammar_errors' => 0,
                'error_ratio' => 0.0,
                'avg_sentence_len' => 0.0,
            ];
        }

        $words = preg_split('/[^a-zA-Z\']+/', strtolower($text));
        $words = array_values(array_filter($words));
        $wordCount = count($words);
        if ($wordCount === 0) {
            return [
                'accuracy' => 0,
                'word_count' => 0,
                'target_words' => $minWords > 0 ? $minWords : 180,
                'unique_ratio' => 0.0,
                'marker_count' => 0,
                'grammar_errors' => 0,
                'error_ratio' => 0.0,
                'avg_sentence_len' => 0.0,
            ];
        }

        $targetWords = $minWords > 0 ? $minWords : 180;
        $uniqueRatio = count(array_unique($words)) / $wordCount;
        $markers = ['because', 'however', 'therefore', 'for example', 'for instance', 'firstly', 'secondly', 'finally', 'in conclusion', 'on the other hand', 'although', 'while'];
        $lower = strtolower($text);
        $markerCount = 0;
        foreach ($markers as $marker) {
            $markerCount += substr_count($lower, $marker);
        }

        $sentences = preg_split('/[.!?]+/', $text);
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        $sentenceCount = max(1, count($sentences));
        $avgSentenceLen = $wordCount / $sentenceCount;

        $grammarErrors = $this->estimateGrammarErrorCount($lower);
        $errorRatio = $grammarErrors / max(1, $wordCount);

        $lengthScore = min(15, (int) round(($wordCount / max(1, $targetWords)) * 15));
        $lexicalScore = match (true) {
            $uniqueRatio >= 0.60 => 15,
            $uniqueRatio >= 0.54 => 12,
            $uniqueRatio >= 0.48 => 9,
            $uniqueRatio >= 0.42 => 6,
            default => 3,
        };
        $coherenceScore = min(10, $markerCount * 2);
        $sentenceScore = match (true) {
            $avgSentenceLen >= 11 && $avgSentenceLen <= 24 => 10,
            $avgSentenceLen >= 9 && $avgSentenceLen <= 28 => 8,
            $avgSentenceLen >= 7 => 6,
            default => 3,
        };
        $grammarScore = match (true) {
            $errorRatio < 0.01 => 15,
            $errorRatio < 0.02 => 12,
            $errorRatio < 0.03 => 9,
            $errorRatio < 0.04 => 6,
            $errorRatio < 0.05 => 4,
            default => 2,
        };

        $total = 30 + $lengthScore + $lexicalScore + $coherenceScore + $sentenceScore + $grammarScore;

        if ($wordCount < 60) {
            $total = min($total, 35);
        } elseif ($wordCount < 100) {
            $total = min($total, 50);
        }

        if ($errorRatio >= 0.05) {
            $total -= 12;
        }
        if ($errorRatio >= 0.08) {
            $total -= 10;
        }
        if ($markerCount <= 1) {
            $total -= 6;
        }
        if ($wordCount < $targetWords) {
            $total -= min(15, (int) round((($targetWords - $wordCount) / max(1, $targetWords)) * 20));
        }

        $accuracy = max(20, min(90, (int) round($total)));

        return [
            'accuracy' => $accuracy,
            'word_count' => $wordCount,
            'target_words' => $targetWords,
            'unique_ratio' => $uniqueRatio,
            'marker_count' => $markerCount,
            'grammar_errors' => $grammarErrors,
            'error_ratio' => $errorRatio,
            'avg_sentence_len' => $avgSentenceLen,
        ];
    }

    private function estimateGrammarErrorCount(string $lowerText): int
    {
        $patterns = [
            '/\bi\s+not\b/',
            '/\b(he|she|it)\s+(do|have|go|say|make|take|come|see|know|think|want|use|need|work|study|learn|give|find|help|feel|become)\b/',
            '/\bthey\s+is\b/',
            '/\bwe\s+is\b/',
            '/\byou\s+is\b/',
            '/\bstudents\s+is\b/',
            '/\bthis\s+save\b/',
            '/\bthis\s+help\b/',
            '/\bthere\s+have\b/',
            '/\bonline\s+learning\s+have\b/',
            '/\bpeople\s+who\s+working\b/',
            '/\bnot\s+need\b/',
            '/\bnot\s+agree\b/',
            '/\bnot\s+focus\b/',
            '/\bnot\s+understand\b/',
            '/\bthere\s+is\s+\w+\s+(people|students|books|cars|things)\b/',
            '/\b(a|an)\s+(students|people|children|teachers|cars|things|books)\b/',
            '/\bcan\s+be\s+watch\b/',
            '/\bclassroom\s+is\s+still\s+need\b/',
            '/\bonline\s+course\s+is\b/',
            '/\bmany\s+big\s+problem\b/',
            '/\bthis\s+is\s+not\s+enough\s+for\s+future\s+job\b/',
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $lowerText, $matches)) {
                $count += count($matches[0]);
            }
        }

        return $count;
    }
    private function buildLocalPracticeFeedback(WritingTask $task, string $responseText, int $wordCount): array
    {
        $minWords = (int) ($task->min_words ?: 250);
        $maxWords = (int) ($task->max_words ?: 340);
        $analysis = $this->analyzeWritingResponse($responseText, $minWords);
        $keywordCoverage = $this->calculatePromptKeywordCoverage((string) ($task->prompt ?? ''), $responseText);
        $paragraphCount = $this->countParagraphs($responseText);
        $sample = $this->extractSamplePhrase($responseText);
        $sentences = $this->splitSentences($responseText);
        $sentenceStats = $this->analyzeSentenceVariety($sentences);
        $errorMap = $this->buildErrorMap($responseText, $sentences, $task);

        $positionClear = preg_match('/(i agree|i disagree|i strongly agree|i strongly disagree|in my opinion|i believe|i think)/i', $responseText) === 1;
        $exampleCount = $this->countSignalPhrases($responseText, ['for example', 'for instance', 'such as']);
        $offTopicParagraphs = $this->countOffTopicParagraphs((string) ($task->prompt ?? ''), $responseText);
        $weakIntro = $this->hasWeakIntro($responseText);
        $weakConclusion = $this->hasWeakConclusion($responseText);
        $isTask1 = $this->isTaskOne($task);
        $taskOneInfo = $isTask1 ? $this->analyzeTaskOneRequirements($task, $responseText) : [];
        $taskTwoInfo = !$isTask1 ? $this->analyzeTaskTwoRequirements($task, $responseText) : [];
        $nonsense = $this->detectNonsenseTokens($responseText);

        $taskScore = 35;
        if ($isTask1) {
            $taskScore = 30;
            $taskScore += min(16, (int) round($keywordCoverage / 6));
            $taskScore += !empty($taskOneInfo['has_data']) ? 16 : -14;
            $taskScore += !empty($taskOneInfo['has_comparison']) ? 10 : -10;
            $taskScore += !empty($taskOneInfo['has_overview']) ? 8 : -8;
            $taskScore += !empty($taskOneInfo['has_year']) ? 6 : -6;
            $taskScore -= !empty($taskOneInfo['has_opinion']) ? 8 : 0;
            $taskScore -= !empty($taskOneInfo['has_advice']) ? 8 : 0;
            $taskScore -= !empty($taskOneInfo['has_conclusion']) ? 6 : 0;
            $taskScore -= min(18, $offTopicParagraphs * 6);
        } else {
            $taskScore += min(20, (int) round($keywordCoverage / 5));
            $taskScore += $positionClear ? 10 : -8;
            $taskScore += min(12, $exampleCount * 6);
            $taskScore -= min(15, $offTopicParagraphs * 6);
            $taskScore -= $weakConclusion ? 8 : 0;
            $taskScore -= $weakIntro ? 4 : 0;
        }
        if ($wordCount < $minWords) {
            $taskScore -= min(14, (int) round((($minWords - $wordCount) / max(1, $minWords)) * 20));
        }
        $taskScore = $isTask1 ? max(15, min(90, $taskScore)) : max(20, min(95, $taskScore));

        $coherenceScore = 38;
        $coherenceScore += min(15, $paragraphCount * 4);
        $coherenceScore += min(14, (int) round($analysis['marker_count'] * 2.5));
        $coherenceScore += min(12, (int) round($sentenceStats['complex_ratio'] * 20));
        $coherenceScore -= min(12, (int) $sentenceStats['jump_count'] * 3);
        $coherenceScore -= $paragraphCount <= 1 ? 8 : 0;
        $coherenceScore = max(20, min(95, $coherenceScore));

        $lexicalScore = 36;
        $lexicalScore += min(22, (int) round($analysis['unique_ratio'] * 30));
        $lexicalScore += min(10, (int) round($keywordCoverage / 10));
        $lexicalScore -= min(15, ($errorMap['vocabulary']['repetition'] ?? 0) * 2);
        $lexicalScore -= min(12, ($errorMap['vocabulary']['wrong_collocation'] ?? 0) * 3);
        $lexicalScore -= min(8, ($errorMap['vocabulary']['simple_word_overuse'] ?? 0));
        $lexicalScore = max(20, min(95, $lexicalScore));

        $grammarErrors = array_sum($errorMap['grammar']);
        $grammarScore = 90;
        $grammarScore -= min(60, $grammarErrors * 6);
        $grammarScore -= min(10, (int) round(($analysis['error_ratio'] ?? 0) * 100));
        $grammarScore = max(20, min(95, $grammarScore));

        $sentenceVarietyScore = 40;
        $sentenceVarietyScore += min(24, (int) round($sentenceStats['complex_ratio'] * 35));
        $sentenceVarietyScore += min(10, (int) round($sentenceStats['relative_clause_ratio'] * 25));
        $sentenceVarietyScore -= min(16, max(0, (int) round(($sentenceStats['simple_ratio'] - 0.6) * 40)));
        $sentenceVarietyScore = max(20, min(95, $sentenceVarietyScore));

        $healthIndex = (int) round(
            ($taskScore * 0.26)
            + ($coherenceScore * 0.20)
            + ($lexicalScore * 0.19)
            + ($grammarScore * 0.22)
            + ($sentenceVarietyScore * 0.13)
        );
        $healthIndex = max(20, min(95, $healthIndex));

        if ($isTask1) {
            $accuracyCap = 100;
            if (empty($taskOneInfo['has_data'])) {
                $accuracyCap = min($accuracyCap, 45);
            }
            if (empty($taskOneInfo['has_comparison'])) {
                $accuracyCap = min($accuracyCap, 55);
            }
            if (empty($taskOneInfo['has_overview'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            if (empty($taskOneInfo['has_year'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            if (!empty($taskOneInfo['has_opinion'])) {
                $accuracyCap = min($accuracyCap, 65);
            }
            if (!empty($taskOneInfo['has_advice']) || !empty($taskOneInfo['has_conclusion'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            $violationCount = 0;
            if (!empty($taskOneInfo['has_opinion'])) {
                $violationCount++;
            }
            if (!empty($taskOneInfo['has_advice'])) {
                $violationCount++;
            }
            if (!empty($taskOneInfo['has_conclusion'])) {
                $violationCount++;
            }
            if ($violationCount >= 2) {
                $accuracyCap = min($accuracyCap, 35);
            } elseif ($violationCount === 1) {
                $accuracyCap = min($accuracyCap, 40);
            }
            if (!empty($nonsense['count'])) {
                $accuracyCap = min($accuracyCap, ($nonsense['critical'] ?? false) ? 35 : 45);
            }
            if ($offTopicParagraphs > 0) {
                $accuracyCap = min($accuracyCap, 55);
            }

            $taskCap = 100;
            if (empty($taskOneInfo['has_data'])) {
                $taskCap = min($taskCap, 30);
            }
            if (empty($taskOneInfo['has_comparison'])) {
                $taskCap = min($taskCap, 40);
            }
            if (empty($taskOneInfo['has_overview'])) {
                $taskCap = min($taskCap, 45);
            }
            if (empty($taskOneInfo['has_year'])) {
                $taskCap = min($taskCap, 45);
            }
            if (!empty($taskOneInfo['has_opinion'])) {
                $taskCap = min($taskCap, 50);
            }
            if (!empty($taskOneInfo['has_advice']) || !empty($taskOneInfo['has_conclusion'])) {
                $taskCap = min($taskCap, 45);
            }
            if ($violationCount >= 2) {
                $taskCap = min($taskCap, 30);
            } elseif ($violationCount === 1) {
                $taskCap = min($taskCap, 35);
            }
            if (!empty($nonsense['count'])) {
                $taskCap = min($taskCap, ($nonsense['critical'] ?? false) ? 30 : 40);
            }
            if ($offTopicParagraphs > 0) {
                $taskCap = min($taskCap, 40);
            }

            $healthIndex = min($healthIndex, $accuracyCap);
            $taskScore = min($taskScore, $taskCap);
        } else {
            $accuracyCap = 100;
            if (empty($taskTwoInfo['meets_word_count'])) {
                $accuracyCap = min($accuracyCap, 45);
            }
            if (empty($taskTwoInfo['has_position'])) {
                $accuracyCap = min($accuracyCap, 55);
            }
            if (empty($taskTwoInfo['has_examples'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            if (empty($taskTwoInfo['has_paragraphs'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            if (empty($taskTwoInfo['has_conclusion'])) {
                $accuracyCap = min($accuracyCap, 65);
            }
            if (empty($taskTwoInfo['has_intro'])) {
                $accuracyCap = min($accuracyCap, 70);
            }
            if (!empty($nonsense['count'])) {
                $accuracyCap = min($accuracyCap, ($nonsense['critical'] ?? false) ? 35 : 45);
            }
            if ($offTopicParagraphs > 0) {
                $accuracyCap = min($accuracyCap, 50);
            }

            $taskCap = 100;
            if (empty($taskTwoInfo['meets_word_count'])) {
                $taskCap = min($taskCap, 35);
            }
            if (empty($taskTwoInfo['has_position'])) {
                $taskCap = min($taskCap, 45);
            }
            if (empty($taskTwoInfo['has_examples'])) {
                $taskCap = min($taskCap, 50);
            }
            if (empty($taskTwoInfo['has_paragraphs'])) {
                $taskCap = min($taskCap, 50);
            }
            if (empty($taskTwoInfo['has_conclusion'])) {
                $taskCap = min($taskCap, 55);
            }
            if (!empty($nonsense['count'])) {
                $taskCap = min($taskCap, ($nonsense['critical'] ?? false) ? 30 : 40);
            }
            if ($offTopicParagraphs > 0) {
                $taskCap = min($taskCap, 40);
            }

            $healthIndex = min($healthIndex, $accuracyCap);
            $taskScore = min($taskScore, $taskCap);
        }

        $taskIssues = [];
        if ($isTask1) {
            $taskIssues = $this->buildTaskOneIssues($taskOneInfo, $offTopicParagraphs);
            if (empty($taskIssues)) {
                $taskIssues[] = $this->localizedText(
                    "Grafikdagi asosiy trendlar berilgan, endi 2-3 aniq raqam bilan mustahkamlang.",
                    "Основные тренды указаны; добавьте 2-3 точных значения для поддержки.",
                    "Main trends are mentioned; add 2-3 exact figures to support them."
                );
            }
        } else {
            $taskIssues = $this->buildTaskTwoIssues($taskTwoInfo);
            if (empty($taskIssues)) {
                $taskIssues[] = $this->localizedText(
                    "Task javobi yaxshi yo'nalishda, endi dalillarni yanada chuqurlashtiring.",
                    "Task response is on the right track; now deepen your support and analysis.",
                    "Task response is on the right track; now deepen your support and analysis."
                );
            }
        }

        $coherenceIssues = [];
        if (($analysis['marker_count'] ?? 0) <= 2) {
            $coherenceIssues[] = $this->localizedText(
                "Transition yetishmaydi: moreover/however/as a result kabi bog'lovchilar qo'shing.",
                "Transitions are limited: add connectors such as moreover/however/as a result.",
                "Transitions are limited: add connectors such as moreover/however/as a result."
            );
        }
        if (($sentenceStats['jump_count'] ?? 0) > 1) {
            $coherenceIssues[] = $this->localizedText(
                "Ba'zi joylarda mantiqiy sakrash bor; har paragraf boshida topic sentence yozing.",
                "Some idea jumps are present; start each paragraph with a clear topic sentence.",
                "Some idea jumps are present; start each paragraph with a clear topic sentence."
            );
        }
        if ($paragraphCount <= 1) {
            $coherenceIssues[] = $this->localizedText(
                "Matnni kamida 4 paragrafga ajrating (intro, body1, body2, conclusion).",
                "Split the essay into at least 4 paragraphs (intro, body1, body2, conclusion).",
                "Split the essay into at least 4 paragraphs (intro, body1, body2, conclusion)."
            );
        }
        if (empty($coherenceIssues)) {
            $coherenceIssues[] = $this->localizedText(
                "Matn oqimi umumiy yaxshi, keyingi qadam - transitionlarni tabiiyroq qilish.",
                "Overall flow is good; next step is making transitions more natural.",
                "Overall flow is good; next step is making transitions more natural."
            );
        }

        $lexicalIssues = [];
        if (($errorMap['vocabulary']['repetition'] ?? 0) >= 3) {
            $lexicalIssues[] = $this->localizedText(
                "Takror so'zlar ko'p: ayniqsa umumiy iboralarni sinonim bilan almashtiring.",
                "Word repetition is high: replace generic phrases with precise synonyms.",
                "Word repetition is high: replace generic phrases with precise synonyms."
            );
        }
        if (($errorMap['vocabulary']['wrong_collocation'] ?? 0) > 0) {
            $lexicalIssues[] = $this->localizedText(
                "Collocation xatolari bor (masalan: make decision -> make a decision).",
                "Collocation errors appear (e.g., make decision -> make a decision).",
                "Collocation errors appear (e.g., make decision -> make a decision)."
            );
        }
        if (($errorMap['vocabulary']['simple_word_overuse'] ?? 0) >= 3) {
            $lexicalIssues[] = $this->localizedText(
                "Juda sodda so'zlar ko'p takrorlangan (very/good/bad/many).",
                "Simple vocabulary is overused (very/good/bad/many).",
                "Simple vocabulary is overused (very/good/bad/many)."
            );
        }
        if (empty($lexicalIssues)) {
            $lexicalIssues[] = $this->localizedText(
                "Lug'at yomon emas, lekin academic collocationlarni ko'paytirish kerak.",
                "Vocabulary is acceptable, but you should add more academic collocations.",
                "Vocabulary is acceptable, but you should add more academic collocations."
            );
        }

        $grammarIssues = [];
        if (($errorMap['grammar']['articles'] ?? 0) > 0) {
            $grammarIssues[] = $this->localizedText(
                "Article xatolari topildi: a/an/the ishlatilishini alohida tekshiring.",
                "Article errors are present: review a/an/the usage separately.",
                "Article errors are present: review a/an/the usage separately."
            );
        }
        if (($errorMap['grammar']['subject_verb_agreement'] ?? 0) > 0) {
            $grammarIssues[] = $this->localizedText(
                "Subject-verb agreement xatolari bor.",
                "Subject-verb agreement errors are present.",
                "Subject-verb agreement errors are present."
            );
        }
        if (($errorMap['grammar']['tenses'] ?? 0) > 0) {
            $grammarIssues[] = $this->localizedText(
                "Tense mosligi ba'zi joylarda buzilgan.",
                "Tense consistency breaks in several places.",
                "Tense consistency breaks in several places."
            );
        }
        if (($errorMap['grammar']['run_on_sentences'] ?? 0) > 0) {
            $grammarIssues[] = $this->localizedText(
                "Run-on sentence mavjud: uzun gaplarni 2 ga bo'ling.",
                "Run-on sentences appear: split very long sentences into two.",
                "Run-on sentences appear: split very long sentences into two."
            );
        }
        if (empty($grammarIssues)) {
            $grammarIssues[] = $this->localizedText(
                "Grammar nazorati o'rtacha; keyingi bosqichda murakkab gaplarda aniqlikni oshiring.",
                "Grammar control is moderate; next step is improving accuracy in complex sentences.",
                "Grammar control is moderate; next step is improving accuracy in complex sentences."
            );
        }

        $varietyIssues = [];
        if (($sentenceStats['simple_ratio'] ?? 1) > 0.7) {
            $varietyIssues[] = $this->localizedText(
                "Simple gaplar ulushi juda yuqori. Complex gaplarni ko'paytiring.",
                "The share of simple sentences is too high. Increase complex sentence usage.",
                "The share of simple sentences is too high. Increase complex sentence usage."
            );
        }
        if (($sentenceStats['relative_clause_ratio'] ?? 0) < 0.15) {
            $varietyIssues[] = $this->localizedText(
                "Relative clause (which/that/who) kam ishlatilgan.",
                "Relative clauses (which/that/who) are rarely used.",
                "Relative clauses (which/that/who) are rarely used."
            );
        }
        if (empty($varietyIssues)) {
            $varietyIssues[] = $this->localizedText(
                "Gap turlari xilma-xilligi yaxshi yo'nalishda.",
                "Sentence variety is moving in a good direction.",
                "Sentence variety is moving in a good direction."
            );
        }

        $healthLevel = $healthIndex >= 80 ? 'strong' : ($healthIndex >= 60 ? 'developing' : 'weak_foundation');
        $healthLabel = $healthLevel === 'strong'
            ? 'Strong'
            : ($healthLevel === 'developing' ? 'Developing' : 'Weak foundation');

        $summary = $this->localizedText(
            "Writing practice natijasi diagnostika asosida berildi: eng katta o'sish nuqtalari task depth, grammatika aniqligi va gap xilma-xilligi.",
            "This writing practice result is diagnostic-first: biggest growth areas are idea depth, grammar control, and sentence variety.",
            "This writing practice result is diagnostic-first: biggest growth areas are idea depth, grammar control, and sentence variety."
        );

        $immediateFixes = [
            $taskIssues[0] ?? '',
            $this->localizedText(
                "2-paragrafga real hayotdan bitta aniq misol qo'shing.",
                "Add one concrete real-life example in paragraph 2.",
                "Add one concrete real-life example in paragraph 2."
            ),
            $this->localizedText(
                "Conclusionni 1-2 gap bilan mustahkamlang.",
                "Strengthen the conclusion with 1-2 clear sentences.",
                "Strengthen the conclusion with 1-2 clear sentences."
            ),
        ];
        $shortTermFocus = [
            $this->localizedText("Articles (a/the) ustida kunlik 15 daqiqa mashq qiling.", "Practice articles (a/the) for 15 minutes daily.", "Practice articles (a/the) for 15 minutes daily."),
            $this->localizedText("Opinion essay strukturasini template bilan qayta yozing.", "Rebuild opinion essay structure using a fixed template.", "Rebuild your opinion essay structure using a fixed template."),
            $this->localizedText("Har essaydan keyin 5 ta collocation ro'yxatini tuzing.", "After each essay, record 5 useful collocations.", "After each essay, record 5 useful collocations."),
        ];
        $longTermGrowth = [
            $this->localizedText("Advanced connectorlar: nevertheless, consequently, whereas.", "Advanced connectors: nevertheless, consequently, whereas.", "Advanced connectors: nevertheless, consequently, whereas."),
            $this->localizedText("Complex sentences: although/while/which orqali murakkab gaplar sonini oshiring.", "Increase complex sentence use with although/while/which.", "Increase complex sentence use with although/while/which."),
            $this->localizedText("Har hafta 2 ta essayni qayta tahrir qilib xato xaritasini tozalang.", "Each week, rewrite 2 essays and reduce your error map counts.", "Each week, rewrite 2 essays and reduce your error-map counts."),
        ];

        $diagnostic = [
            'task_response' => ['percent' => $taskScore, 'notes' => $taskIssues],
            'coherence_cohesion' => ['percent' => $coherenceScore, 'notes' => $coherenceIssues],
            'lexical_resource' => ['percent' => $lexicalScore, 'notes' => $lexicalIssues],
            'grammar_accuracy' => ['percent' => $grammarScore, 'notes' => $grammarIssues],
            'sentence_variety' => ['percent' => $sentenceVarietyScore, 'notes' => $varietyIssues],
        ];

        $corrections = $this->detectGrammarIssues($responseText);
        if (empty($corrections)) {
            $corrections[] = [
                'issue' => $this->localizedText('Sentence upgrade', 'Sentence upgrade', 'Sentence upgrade'),
                'before' => $sample,
                'after' => $sample.' because ... therefore ...',
            ];
        }
        $textErrors = array_map(static function (array $item) {
            return [
                'before' => (string) ($item['before'] ?? ''),
                'after' => (string) ($item['after'] ?? ''),
                'reason' => (string) ($item['issue'] ?? ''),
            ];
        }, $corrections);

        return [
            'engine' => 'local_practice_v3',
            'module' => 'writing',
            'mode' => 'practice',
            'task_type' => strtoupper((string) ($task->task_type ?? 'TASK2')),
            'cefr_level' => (string) ($task->difficulty ?? ''),
            'word_range' => ['min' => $minWords, 'max' => $maxWords],
            'word_count' => $wordCount,
            'accuracy_percent' => $healthIndex,
            'overall_band' => null,
            'diagnostic' => $diagnostic,
            'error_map' => $errorMap,
            'improvement_plan' => [
                'immediate_fixes' => array_values(array_filter($immediateFixes)),
                'short_term_focus' => array_values(array_filter($shortTermFocus)),
                'long_term_growth' => array_values(array_filter($longTermGrowth)),
            ],
            'health_index' => [
                'overall_percent' => $healthIndex,
                'level' => $healthLevel,
                'label' => $healthLabel,
            ],
            // Compatibility keys for existing widgets
            'criteria' => [
                'task_response' => ['band' => null, 'notes' => implode(' ', array_slice($taskIssues, 0, 2))],
                'coherence_cohesion' => ['band' => null, 'notes' => implode(' ', array_slice($coherenceIssues, 0, 2))],
                'lexical_resource' => ['band' => null, 'notes' => implode(' ', array_slice($lexicalIssues, 0, 2))],
                'grammar_range_accuracy' => ['band' => null, 'notes' => implode(' ', array_slice($grammarIssues, 0, 2))],
            ],
            'strengths' => $this->extractStrengthsFromDiagnostic($diagnostic),
            'weaknesses' => array_values(array_unique(array_slice(array_merge($taskIssues, $grammarIssues, $lexicalIssues), 0, 6))),
            'improvements' => array_values(array_unique(array_slice(array_merge($immediateFixes, $shortTermFocus), 0, 5))),
            'corrections' => array_slice($corrections, 0, 5),
            'text_errors' => array_slice($textErrors, 0, 5),
            'examples' => [
                $this->localizedText(
                    "Model gap: \"Although online learning is flexible, it cannot fully replace classroom feedback.\"",
                    "Model sentence: \"Although online learning is flexible, it cannot fully replace classroom feedback.\"",
                    "Model sentence: \"Although online learning is flexible, it cannot fully replace classroom feedback.\""
                ),
                $this->localizedText(
                    "Model gap: \"This issue matters because students need both convenience and structured guidance.\"",
                    "Model sentence: \"This issue matters because students need both convenience and structured guidance.\"",
                    "Model sentence: \"This issue matters because students need both convenience and structured guidance.\""
                ),
            ],
            'summary' => $summary,
            'upgrade_hint' => '',
        ];
    }

    private function calculatePromptKeywordCoverage(string $prompt, string $response): int
    {
        $promptHasCyrillic = preg_match('/[а-яё]/iu', $prompt) === 1;
        $responseHasCyrillic = preg_match('/[а-яё]/iu', $response) === 1;
        $promptHasLatin = preg_match('/[a-z]/i', $prompt) === 1;
        $responseHasLatin = preg_match('/[a-z]/i', $response) === 1;
        if (($promptHasCyrillic && $responseHasLatin && !$responseHasCyrillic)
            || ($promptHasLatin && $responseHasCyrillic && !$responseHasLatin)) {
            return 60;
        }

        $promptWords = preg_split('/[^a-zA-Z]+/', Str::lower($prompt));
        $promptWords = array_values(array_unique(array_filter($promptWords, function ($word) {
            return strlen((string) $word) >= 4;
        })));

        if (empty($promptWords)) {
            return 60;
        }

        $responseLower = Str::lower($response);
        $matched = 0;
        foreach ($promptWords as $word) {
            if (str_contains($responseLower, $word)) {
                $matched++;
            }
        }

        $coverage = (int) round(($matched / count($promptWords)) * 100);
        if ($coverage < 15) {
            return 35;
        }

        return $coverage;
    }

    private function countParagraphs(string $text): int
    {
        $parts = preg_split('/\R{2,}/', trim($text));
        $parts = array_values(array_filter(array_map('trim', $parts)));

        if (count($parts) <= 1) {
            $lines = preg_split('/\R+/', trim($text));
            $lines = array_values(array_filter(array_map('trim', $lines)));
            return max(1, count($lines));
        }

        return count($parts);
    }

    private function detectGrammarIssues(string $text): array
    {
        $patterns = [
            ['/\bi not\b/i', 'Negation form', "I not agree", "I do not agree"],
            ['/\\bonline learning have\\b/i', 'Subject-verb agreement', 'online learning have', 'online learning has'],
            ['/\\bstudents is\\b/i', 'Plural subject agreement', 'students is', 'students are'],
            ['/\\bthis save\\b/i', 'Third-person verb', 'this save time', 'this saves time'],
            ['/\\bthere have\\b/i', 'Existential structure', 'there have many...', 'there are many...'],
            ['/\\bcan be watch\\b/i', 'Passive form', 'can be watch', 'can be watched'],
        ];

        $issues = [];
        foreach ($patterns as [$regex, $issue, $before, $after]) {
            if (preg_match($regex, $text) === 1) {
                $issues[] = [
                    'issue' => (string) $issue,
                    'before' => (string) $before,
                    'after' => (string) $after,
                ];
            }
        }

        return $issues;
    }

    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text));
        $parts = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $parts)));
        if (empty($parts)) {
            return [trim($text)];
        }

        return $parts;
    }

    private function analyzeSentenceVariety(array $sentences): array
    {
        $count = max(1, count($sentences));
        $simple = 0;
        $complex = 0;
        $relative = 0;
        $jumpCount = 0;

        foreach ($sentences as $sentence) {
            $lower = Str::lower((string) $sentence);
            $words = preg_split('/[^a-zA-Z\']+/', $lower);
            $wordCount = count(array_filter($words));

            $hasComplexMarker = preg_match('/\b(although|while|whereas|because|since|which|that|who|if|unless|however|therefore)\b/i', $sentence) === 1;
            if ($hasComplexMarker || str_contains($sentence, ',')) {
                $complex++;
            } else {
                $simple++;
            }

            if (preg_match('/\b(which|that|who|whom|whose)\b/i', $sentence) === 1) {
                $relative++;
            }

            if ($wordCount >= 28 && preg_match('/\b(and|but|so)\b.*\b(and|but|so)\b/i', $sentence) === 1) {
                $jumpCount++;
            }
        }

        return [
            'simple_ratio' => $simple / $count,
            'complex_ratio' => $complex / $count,
            'relative_clause_ratio' => $relative / $count,
            'jump_count' => $jumpCount,
        ];
    }

    private function buildErrorMap(string $responseText, array $sentences, WritingTask $task): array
    {
        $lower = Str::lower($responseText);
        $wordCounts = array_count_values(array_filter(preg_split('/[^a-zA-Z]+/', $lower), fn ($w) => strlen((string) $w) >= 3));

        $repetition = 0;
        foreach ($wordCounts as $word => $freq) {
            if ($freq >= 4 && !in_array($word, ['the', 'and', 'that', 'with', 'from', 'this', 'have', 'many'], true)) {
                $repetition += ($freq - 3);
            }
        }

        $simpleWords = ['very', 'good', 'bad', 'many', 'thing', 'important', 'big'];
        $simpleWordOveruse = 0;
        foreach ($simpleWords as $word) {
            $simpleWordOveruse += max(0, (($wordCounts[$word] ?? 0) - 1));
        }

        $wrongCollocation = 0;
        $wrongCollocation += preg_match_all('/\bmake decision\b/i', $responseText);
        $wrongCollocation += preg_match_all('/\bdo mistake\b/i', $responseText);
        $wrongCollocation += preg_match_all('/\bdiscuss about\b/i', $responseText);
        $wrongCollocation += preg_match_all('/\bdepend of\b/i', $responseText);

        $articles = 0;
        $articles += preg_match_all('/\b(a|an)\s+(students|people|children|teachers|books|things)\b/i', $responseText);
        $articles += preg_match_all('/\bis very common topic\b/i', $responseText);
        $articles += preg_match_all('/\bis common topic\b/i', $responseText);

        $tenses = 0;
        $tenses += preg_match_all('/\bi am agree\b/i', $responseText);
        $tenses += preg_match_all('/\bi was agree\b/i', $responseText);
        $tenses += preg_match_all('/\bthis save\b/i', $responseText);
        $tenses += preg_match_all('/\b(it|technology|education|learning)\s+have\b/i', $responseText);
        $tenses += preg_match_all('/\b(it|technology|education|learning)\s+change\b/i', $responseText);

        $prepositions = 0;
        $prepositions += preg_match_all('/\bdepend of\b/i', $responseText);
        $prepositions += preg_match_all('/\bdiscuss about\b/i', $responseText);
        $prepositions += preg_match_all('/\bin the internet\b/i', $responseText);
        $prepositions += preg_match_all('/\bdon\'?t need go\b/i', $responseText);

        $subjectVerb = 0;
        $subjectVerb += preg_match_all('/\b(students|people|children)\s+is\b/i', $responseText);
        $subjectVerb += preg_match_all('/\b(he|she|it)\s+have\b/i', $responseText);
        $subjectVerb += preg_match_all('/\bonline learning have\b/i', $responseText);
        $subjectVerb += preg_match_all('/\b(technology|education)\s+is\b.*\b(topic|things)\b/i', $responseText);
        $subjectVerb += preg_match_all('/\b(student|he|she|it)\s+don\'?t\b/i', $responseText);
        $subjectVerb += preg_match_all('/\btechnology help\b/i', $responseText);

        $runOn = 0;
        foreach ($sentences as $sentence) {
            $w = count(array_filter(preg_split('/[^a-zA-Z\']+/', Str::lower((string) $sentence))));
            if ($w >= 30 && preg_match('/\b(and|but|so)\b.*\b(and|but|so)\b/i', (string) $sentence) === 1) {
                $runOn++;
            }
        }

        $wordCount = Str::wordCount($responseText);
        $minWords = (int) ($task->min_words ?: 0);
        $paragraphCount = $this->countParagraphs($responseText);
        $isTask1 = $this->isTaskOne($task);
        $minParagraphs = $isTask1 ? 3 : 4;
        $missingParagraphs = $paragraphCount < $minParagraphs;
        $lowWordCount = $minWords > 0 && $wordCount < $minWords;
        $nonsense = $this->detectNonsenseTokens($responseText);

        $isTask1 = $this->isTaskOne($task);
        $taskOneInfo = $isTask1 ? $this->analyzeTaskOneRequirements($task, $responseText) : [];
        $missingExample = $isTask1
            ? false
            : $this->countSignalPhrases($responseText, ['for example', 'for instance', 'such as']) < 2;
        $unclearPosition = $isTask1
            ? false
            : preg_match('/\b(i agree|i disagree|in my opinion|i believe|i think)\b/i', $responseText) !== 1;

        return [
            'grammar' => [
                'articles' => (int) $articles,
                'tenses' => (int) $tenses,
                'prepositions' => (int) $prepositions,
                'subject_verb_agreement' => (int) $subjectVerb,
                'run_on_sentences' => (int) $runOn,
            ],
            'vocabulary' => [
                'repetition' => (int) $repetition,
                'wrong_collocation' => (int) $wrongCollocation,
                'simple_word_overuse' => (int) $simpleWordOveruse,
            ],
            'structure' => [
                'weak_intro' => $this->hasWeakIntro($responseText),
                'missing_example' => $missingExample,
                'unclear_position' => $unclearPosition,
                'weak_conclusion' => $this->hasWeakConclusion($responseText),
                'off_topic_paragraphs' => $this->countOffTopicParagraphs((string) ($task->prompt ?? ''), $responseText),
                'missing_overview' => $isTask1 ? empty($taskOneInfo['has_overview']) : false,
                'missing_data' => $isTask1 ? empty($taskOneInfo['has_data']) : false,
                'missing_comparison' => $isTask1 ? empty($taskOneInfo['has_comparison']) : false,
                'missing_years' => $isTask1 ? empty($taskOneInfo['has_year']) : false,
                'has_opinion' => $isTask1 ? !empty($taskOneInfo['has_opinion']) : false,
                'missing_paragraphs' => $missingParagraphs,
                'low_word_count' => $lowWordCount,
                'nonsense_detected' => $nonsense['count'] > 0,
                'nonsense_critical' => (bool) ($nonsense['critical'] ?? false),
                'nonsense_count' => (int) ($nonsense['count'] ?? 0),
                'nonsense_words' => $nonsense['examples'] ?? [],
                'task1_advice' => $isTask1 ? !empty($taskOneInfo['has_advice']) : false,
                'task1_conclusion' => $isTask1 ? !empty($taskOneInfo['has_conclusion']) : false,
            ],
        ];
    }

    private function countSignalPhrases(string $text, array $signals): int
    {
        $lower = Str::lower($text);
        $count = 0;
        foreach ($signals as $signal) {
            $count += substr_count($lower, Str::lower($signal));
        }

        return $count;
    }

    private function isTaskOne(?WritingTask $task): bool
    {
        return strtoupper((string) ($task?->task_type ?? '')) === 'TASK1';
    }

    private function analyzeTaskOneRequirements(WritingTask $task, string $responseText): array
    {
        $lower = Str::lower($responseText);
        $hasNumber = preg_match('/\b\d+(?:[\\.,]\d+)?\b/', $responseText) === 1;
        $hasPercent = preg_match('/%|\bpercent(?:age)?\b|\bproportion\b|\bratio\b|\bshare\b/', $lower) === 1;
        $hasData = $hasNumber || $hasPercent;

        $comparisonWords = [
            'increase', 'increased', 'decrease', 'decreased', 'decline', 'declined',
            'rise', 'rose', 'fall', 'fell', 'drop', 'dropped', 'higher', 'lower',
            'more', 'less', 'largest', 'smallest', 'most', 'least', 'compared',
            'whereas', 'while', 'in contrast', 'by contrast', 'difference', 'gap',
            'trend', 'peaked', 'stable', 'remained', 'remain',
        ];
        $hasComparison = false;
        foreach ($comparisonWords as $word) {
            if (str_contains($lower, $word)) {
                $hasComparison = true;
                break;
            }
        }

        $overviewPhrases = ['overall', 'in general', 'generally', 'on the whole'];
        $hasOverview = false;
        foreach ($overviewPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                $hasOverview = true;
                break;
            }
        }

        $expectedYears = [];
        $prompt = (string) ($task->prompt ?? '');
        if ($prompt !== '') {
            preg_match_all('/\b(19|20)\d{2}\b/', $prompt, $matches);
            $expectedYears = array_values(array_unique($matches[0] ?? []));
        }
        $hasYear = false;
        if (!empty($expectedYears)) {
            foreach ($expectedYears as $year) {
                if (str_contains($responseText, $year)) {
                    $hasYear = true;
                    break;
                }
            }
        } else {
            $hasYear = preg_match('/\b(19|20)\d{2}\b/', $responseText) === 1;
        }

        $hasOpinion = preg_match('/\b(in my opinion|i think|i believe|i agree|i disagree|personally|it seems to me|in my view|i would say)\b/i', $responseText) === 1;
        $hasAdvice = preg_match('/\b(should|must|ought to|need to|have to)\b/i', $responseText) === 1;
        $hasConclusion = preg_match('/\b(in conclusion|to conclude|to sum up|in summary|to summarize|to summarise)\b/i', $responseText) === 1;

        return [
            'has_data' => $hasData,
            'has_number' => $hasNumber,
            'has_percent' => $hasPercent,
            'has_comparison' => $hasComparison,
            'has_overview' => $hasOverview,
            'has_year' => $hasYear,
            'has_opinion' => $hasOpinion,
            'has_advice' => $hasAdvice,
            'has_conclusion' => $hasConclusion,
        ];
    }

    private function buildTaskOneIssues(array $taskInfo, int $offTopicParagraphs = 0): array
    {
        $issues = [];
        if (empty($taskInfo['has_overview'])) {
            $issues[] = $this->localizedText(
                "Overview yo'q: umumiy trendni 1-2 gapda ayting.",
                "Нет обзора: дайте общий тренд в 1-2 предложениях.",
                "No overview: state the overall trend in 1-2 sentences."
            );
        }
        if (empty($taskInfo['has_data'])) {
            $issues[] = $this->localizedText(
                "Grafikdagi raqam/foizlar keltirilmagan; kamida 2 ta aniq data point yozing.",
                "Нет чисел/процентов из графика; добавьте минимум 2 точных значения.",
                "No figures/percentages from the chart; add at least 2 specific data points."
            );
        }
        if (empty($taskInfo['has_comparison'])) {
            $issues[] = $this->localizedText(
                "Taqqos yo'q: increase/decrease yoki higher/lower bilan 2 ta solishtirish bering.",
                "Нет сравнений: добавьте 2 сравнения (increase/decrease или higher/lower).",
                "No comparisons: add 2 comparisons (increase/decrease or higher/lower)."
            );
        }
        if (empty($taskInfo['has_year'])) {
            $issues[] = $this->localizedText(
                "Yillar ko'rsatilmagan; promptdagi yillarni aniq yozing.",
                "Годы не указаны; явно упомяните годы из задания.",
                "Years are not mentioned; state the years from the prompt."
            );
        }
        if (!empty($taskInfo['has_opinion'])) {
            $issues[] = $this->localizedText(
                "Shaxsiy fikr emas, faqat grafik ma'lumotini tasvirlang.",
                "Не личное мнение, а описание данных графика.",
                "Avoid personal opinion; describe the chart data only."
            );
        }
        if (!empty($taskInfo['has_advice'])) {
            $issues[] = $this->localizedText(
                "Maslahat (should/must) yozmang; faqat ma'lumotni tasvirlang.",
                "Ne dayte sovetov (should/must); tol'ko opisanie dannyh.",
                "Do not give advice (should/must); describe the data only."
            );
        }
        if (!empty($taskInfo['has_conclusion'])) {
            $issues[] = $this->localizedText(
                "Task 1da xulosa (in conclusion/to conclude) kerak emas.",
                "V Task 1 ne nuzhno zaklyuchenie (in conclusion/to conclude).",
                "Task 1 does not need a conclusion (in conclusion/to conclude)."
            );
        }
        if ($offTopicParagraphs > 0) {
            $issues[] = $this->localizedText(
                "Matn grafikdan chetga chiqqan; faqat ma'lumotlarni tasvirlang.",
                "Текст ушел от графика; описывайте только данные.",
                "You went off the chart topic; describe only the data."
            );
        }

        return $issues;
    }

    private function analyzeTaskTwoRequirements(WritingTask $task, string $responseText): array
    {
        $wordCount = Str::wordCount($responseText);
        $minWords = (int) ($task->min_words ?: 250);
        $paragraphCount = $this->countParagraphs($responseText);
        $exampleCount = $this->countSignalPhrases($responseText, ['for example', 'for instance', 'such as']);
        $positionClear = preg_match('/\b(i agree|i disagree|i strongly agree|i strongly disagree|in my opinion|i believe|i think)\b/i', $responseText) === 1;
        $weakIntro = $this->hasWeakIntro($responseText);
        $weakConclusion = $this->hasWeakConclusion($responseText);
        $offTopicParagraphs = $this->countOffTopicParagraphs((string) ($task->prompt ?? ''), $responseText);

        return [
            'has_position' => $positionClear,
            'has_examples' => $exampleCount >= 2,
            'has_intro' => !$weakIntro,
            'has_conclusion' => !$weakConclusion,
            'has_paragraphs' => $paragraphCount >= 4,
            'meets_word_count' => $wordCount >= $minWords,
            'off_topic' => $offTopicParagraphs > 0,
            'word_count' => $wordCount,
            'min_words' => $minWords,
            'paragraph_count' => $paragraphCount,
        ];
    }

    private function buildTaskTwoIssues(array $taskInfo): array
    {
        $issues = [];
        if (empty($taskInfo['has_position'])) {
            $issues[] = $this->localizedText(
                "Kirishda aniq pozitsiya yozing (I agree/disagree yoki clear thesis).",
                "Vvedenii ukazhite chetkuyu poziciyu (I agree/disagree ili clear thesis).",
                "State a clear position in the introduction (I agree/disagree or a clear thesis)."
            );
        }
        if (empty($taskInfo['has_examples'])) {
            $issues[] = $this->localizedText(
                "Kamida 2 ta aniq misol/dalil qo'shing.",
                "Dobav'te minimum 2 konkretnyh primera/dovoda.",
                "Add at least 2 concrete examples or supporting points."
            );
        }
        if (empty($taskInfo['has_paragraphs'])) {
            $issues[] = $this->localizedText(
                "Matnni kamida 4 paragrafga ajrating (intro, body1, body2, conclusion).",
                "Razdelite tekst na minimum 4 paragrafy (intro, body1, body2, conclusion).",
                "Split the essay into at least 4 paragraphs (intro, body1, body2, conclusion)."
            );
        }
        if (empty($taskInfo['meets_word_count'])) {
            $minWords = (int) ($taskInfo['min_words'] ?? 0);
            $issues[] = $this->localizedText(
                "So'z soni yetarli emas: kamida {$minWords} so'z yozing.",
                "Malo slov: nuzhno minimum {$minWords} slov.",
                "Word count is too low: write at least {$minWords} words."
            );
        }
        if (empty($taskInfo['has_conclusion'])) {
            $issues[] = $this->localizedText(
                "Xulosani 1-2 gap bilan yakunlang.",
                "Zavershite rabotu zaklyucheniyem v 1-2 predlozheniyah.",
                "Finish with a clear 1-2 sentence conclusion."
            );
        }
        if (!empty($taskInfo['off_topic'])) {
            $issues[] = $this->localizedText(
                "Mavzudan chetga chiqqan gaplarni olib tashlang.",
                "Udalite chast' teksta, kotorye uhodyat ot temy.",
                "Remove parts that go off-topic."
            );
        }

        return $issues;
    }

    private function detectNonsenseTokens(string $text): array
    {
        $tokens = preg_split('/[^a-zA-Z]+/', $text);
        $gibberish = [];
        $total = 0;
        $maxLen = 0;

        foreach ($tokens as $token) {
            $word = Str::lower((string) $token);
            if ($word === '') {
                continue;
            }
            $len = strlen($word);
            if ($len < 10) {
                continue;
            }

            $vowelCount = preg_match_all('/[aeiouy]/', $word);
            $vowelRatio = $len > 0 ? ($vowelCount / $len) : 0;
            $longConsonantCluster = preg_match('/[^aeiouy]{6,}/', $word) === 1;

            $isGibberish = ($len >= 12 && $vowelRatio < 0.23) || ($len >= 10 && $longConsonantCluster);
            if ($isGibberish) {
                $gibberish[] = $word;
                $total++;
                $maxLen = max($maxLen, $len);
            }
        }

        $unique = array_values(array_unique($gibberish));
        $critical = $total >= 2 || $maxLen >= 18;

        return [
            'count' => $total,
            'unique' => $unique,
            'examples' => array_slice($unique, 0, 3),
            'critical' => $critical,
        ];
    }

    private function applyTaskCaps(array $parsed, WritingTask $task, string $responseText): array
    {
        $taskType = strtoupper((string) ($task->task_type ?? ''));
        $offTopic = (int) data_get($parsed, 'error_map.structure.off_topic_paragraphs', 0);
        $nonsense = $this->detectNonsenseTokens($responseText);
        if (!empty($nonsense['count'])) {
            data_set($parsed, 'error_map.structure.nonsense_detected', true);
            data_set($parsed, 'error_map.structure.nonsense_critical', (bool) ($nonsense['critical'] ?? false));
            data_set($parsed, 'error_map.structure.nonsense_count', (int) ($nonsense['count'] ?? 0));
            data_set($parsed, 'error_map.structure.nonsense_words', $nonsense['examples'] ?? []);
        }

        if ($taskType === 'TASK1') {
            $taskInfo = $this->analyzeTaskOneRequirements($task, $responseText);
            data_set($parsed, 'error_map.structure.has_opinion', !empty($taskInfo['has_opinion']));
            data_set($parsed, 'error_map.structure.task1_opinion', !empty($taskInfo['has_opinion']));
            data_set($parsed, 'error_map.structure.task1_advice', !empty($taskInfo['has_advice']));
            data_set($parsed, 'error_map.structure.task1_conclusion', !empty($taskInfo['has_conclusion']));

            $accuracyCap = 100;
            if (empty($taskInfo['has_data'])) {
                $accuracyCap = min($accuracyCap, 45);
            }
            if (empty($taskInfo['has_comparison'])) {
                $accuracyCap = min($accuracyCap, 55);
            }
            if (empty($taskInfo['has_overview'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            if (empty($taskInfo['has_year'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            if (!empty($taskInfo['has_opinion'])) {
                $accuracyCap = min($accuracyCap, 65);
            }
            if (!empty($taskInfo['has_advice']) || !empty($taskInfo['has_conclusion'])) {
                $accuracyCap = min($accuracyCap, 60);
            }
            $violationCount = 0;
            if (!empty($taskInfo['has_opinion'])) {
                $violationCount++;
            }
            if (!empty($taskInfo['has_advice'])) {
                $violationCount++;
            }
            if (!empty($taskInfo['has_conclusion'])) {
                $violationCount++;
            }
            if ($violationCount >= 2) {
                $accuracyCap = min($accuracyCap, 35);
            } elseif ($violationCount === 1) {
                $accuracyCap = min($accuracyCap, 40);
            }
            if (!empty($nonsense['count'])) {
                $accuracyCap = min($accuracyCap, ($nonsense['critical'] ?? false) ? 35 : 45);
            }
            if ($offTopic > 0) {
                $accuracyCap = min($accuracyCap, 55);
            }

            $accuracy = $this->normalizePercentValue($parsed['accuracy_percent'] ?? null);
            if ($accuracy !== null) {
                $parsed['accuracy_percent'] = min($accuracy, $accuracyCap);
            }
            $health = $this->normalizePercentValue(data_get($parsed, 'health_index.overall_percent'));
            if ($health !== null) {
                data_set($parsed, 'health_index.overall_percent', min($health, $accuracyCap));
            }

            $taskCap = 100;
            if (empty($taskInfo['has_data'])) {
                $taskCap = min($taskCap, 30);
            }
            if (empty($taskInfo['has_comparison'])) {
                $taskCap = min($taskCap, 40);
            }
            if (empty($taskInfo['has_overview'])) {
                $taskCap = min($taskCap, 45);
            }
            if (empty($taskInfo['has_year'])) {
                $taskCap = min($taskCap, 45);
            }
            if (!empty($taskInfo['has_opinion'])) {
                $taskCap = min($taskCap, 50);
            }
            if (!empty($taskInfo['has_advice']) || !empty($taskInfo['has_conclusion'])) {
                $taskCap = min($taskCap, 45);
            }
            if ($violationCount >= 2) {
                $taskCap = min($taskCap, 30);
            } elseif ($violationCount === 1) {
                $taskCap = min($taskCap, 35);
            }
            if (!empty($nonsense['count'])) {
                $taskCap = min($taskCap, ($nonsense['critical'] ?? false) ? 30 : 40);
            }
            if ($offTopic > 0) {
                $taskCap = min($taskCap, 40);
            }

            $taskPercent = $this->normalizePercentValue(data_get($parsed, 'diagnostic.task_response.percent'));
            if ($taskPercent !== null) {
                data_set($parsed, 'diagnostic.task_response.percent', min($taskPercent, $taskCap));
            }

            $extras = $this->buildTaskOneIssues($taskInfo, $offTopic);
            if (!empty($extras)) {
                $note = $this->cleanAiText((string) data_get($parsed, 'criteria.task_response.notes', ''));
                $extraText = implode(' ', array_slice($extras, 0, 2));
                if ($note === '' || strlen($note) < 40 || $this->isGenericFeedbackText($note)) {
                    $note = $extraText;
                } else {
                    $note = trim($note.' '.$extraText);
                }
                data_set($parsed, 'criteria.task_response.notes', $note);
            }

            return $parsed;
        }

        $taskInfo = $this->analyzeTaskTwoRequirements($task, $responseText);
        $accuracyCap = 100;
        if (empty($taskInfo['meets_word_count'])) {
            $accuracyCap = min($accuracyCap, 45);
        }
        if (empty($taskInfo['has_position'])) {
            $accuracyCap = min($accuracyCap, 55);
        }
        if (empty($taskInfo['has_examples'])) {
            $accuracyCap = min($accuracyCap, 60);
        }
        if (empty($taskInfo['has_paragraphs'])) {
            $accuracyCap = min($accuracyCap, 60);
        }
        if (empty($taskInfo['has_conclusion'])) {
            $accuracyCap = min($accuracyCap, 65);
        }
        if (empty($taskInfo['has_intro'])) {
            $accuracyCap = min($accuracyCap, 70);
        }
        if (!empty($nonsense['count'])) {
            $accuracyCap = min($accuracyCap, ($nonsense['critical'] ?? false) ? 35 : 45);
        }
        if ($offTopic > 0) {
            $accuracyCap = min($accuracyCap, 50);
        }

        $accuracy = $this->normalizePercentValue($parsed['accuracy_percent'] ?? null);
        if ($accuracy !== null) {
            $parsed['accuracy_percent'] = min($accuracy, $accuracyCap);
        }
        $health = $this->normalizePercentValue(data_get($parsed, 'health_index.overall_percent'));
        if ($health !== null) {
            data_set($parsed, 'health_index.overall_percent', min($health, $accuracyCap));
        }

        $taskCap = 100;
        if (empty($taskInfo['meets_word_count'])) {
            $taskCap = min($taskCap, 35);
        }
        if (empty($taskInfo['has_position'])) {
            $taskCap = min($taskCap, 45);
        }
        if (empty($taskInfo['has_examples'])) {
            $taskCap = min($taskCap, 50);
        }
        if (empty($taskInfo['has_paragraphs'])) {
            $taskCap = min($taskCap, 50);
        }
        if (empty($taskInfo['has_conclusion'])) {
            $taskCap = min($taskCap, 55);
        }
        if (!empty($nonsense['count'])) {
            $taskCap = min($taskCap, ($nonsense['critical'] ?? false) ? 30 : 40);
        }
        if ($offTopic > 0) {
            $taskCap = min($taskCap, 40);
        }

        $taskPercent = $this->normalizePercentValue(data_get($parsed, 'diagnostic.task_response.percent'));
        if ($taskPercent !== null) {
            data_set($parsed, 'diagnostic.task_response.percent', min($taskPercent, $taskCap));
        }

        $extras = $this->buildTaskTwoIssues($taskInfo);
        if (!empty($extras)) {
            $note = $this->cleanAiText((string) data_get($parsed, 'criteria.task_response.notes', ''));
            $extraText = implode(' ', array_slice($extras, 0, 2));
            if ($note === '' || strlen($note) < 40 || $this->isGenericFeedbackText($note)) {
                $note = $extraText;
            } else {
                $note = trim($note.' '.$extraText);
            }
            data_set($parsed, 'criteria.task_response.notes', $note);
        }

        return $parsed;
    }

    private function enforcePracticeScoreConsistency(array $parsed): array
    {
        $accuracy = $this->normalizePercentValue($parsed['accuracy_percent'] ?? null);
        $derived = $this->deriveAccuracyFromDiagnostic($parsed);
        if ($accuracy === null || $derived === null) {
            return $parsed;
        }

        $structure = (array) data_get($parsed, 'error_map.structure', []);
        $hasMajorViolation = !empty($structure['nonsense_detected'])
            || ((int) ($structure['off_topic_paragraphs'] ?? 0) > 0)
            || !empty($structure['missing_data'])
            || !empty($structure['missing_overview'])
            || !empty($structure['missing_comparison'])
            || !empty($structure['missing_years'])
            || !empty($structure['task1_opinion'])
            || !empty($structure['task1_advice'])
            || !empty($structure['task1_conclusion'])
            || !empty($structure['low_word_count'])
            || !empty($structure['missing_paragraphs'])
            || !empty($structure['missing_example'])
            || !empty($structure['unclear_position']);

        if ($hasMajorViolation) {
            return $parsed;
        }

        if ($derived >= 60 && $accuracy < 60) {
            $accuracy = 60;
        }
        if (($derived - $accuracy) >= 8) {
            $accuracy = $derived;
        }

        $accuracy = (int) max(0, min(100, (int) round((float) $accuracy)));
        $parsed['accuracy_percent'] = $accuracy;
        $health = $this->normalizePercentValue(data_get($parsed, 'health_index.overall_percent'));
        if ($health !== null && $health < $accuracy) {
            data_set($parsed, 'health_index.overall_percent', $accuracy);
        }

        return $parsed;
    }

    private function countOffTopicParagraphs(string $prompt, string $responseText): int
    {
        $promptHasCyrillic = preg_match('/[а-яё]/iu', $prompt) === 1;
        $responseHasCyrillic = preg_match('/[а-яё]/iu', $responseText) === 1;
        $promptHasLatin = preg_match('/[a-z]/i', $prompt) === 1;
        $responseHasLatin = preg_match('/[a-z]/i', $responseText) === 1;
        if (($promptHasCyrillic && $responseHasLatin && !$responseHasCyrillic)
            || ($promptHasLatin && $responseHasCyrillic && !$responseHasLatin)) {
            return 0;
        }

        $promptWords = preg_split('/[^a-zA-Z]+/', Str::lower($prompt));
        $promptWords = array_values(array_unique(array_filter($promptWords, fn ($w) => strlen((string) $w) >= 4)));
        if (empty($promptWords)) {
            return 0;
        }

        $responseLower = Str::lower($responseText);
        $totalHits = 0;
        foreach ($promptWords as $word) {
            if (str_contains($responseLower, $word)) {
                $totalHits++;
            }
        }
        $totalCoverage = (int) round(($totalHits / count($promptWords)) * 100);
        if ($totalCoverage < 12) {
            // If global lexical overlap is extremely low, off-topic paragraph detection is unreliable.
            return 0;
        }

        $paragraphs = preg_split('/\R{2,}/', trim($responseText));
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
        if (empty($paragraphs)) {
            return 0;
        }

        $offTopic = 0;
        foreach ($paragraphs as $paragraph) {
            $lower = Str::lower((string) $paragraph);
            $hits = 0;
            foreach ($promptWords as $word) {
                if (str_contains($lower, $word)) {
                    $hits++;
                }
            }
            $coverage = (int) round(($hits / count($promptWords)) * 100);
            if ($coverage < 10) {
                $offTopic++;
            }
        }

        return $offTopic;
    }

    private function hasWeakIntro(string $responseText): bool
    {
        $paragraphs = preg_split('/\R{2,}/', trim($responseText));
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
        $intro = (string) ($paragraphs[0] ?? '');
        if ($intro === '') {
            return true;
        }

        $introWords = count(array_filter(preg_split('/[^a-zA-Z\']+/', Str::lower($intro))));
        $hasPosition = preg_match('/\b(i agree|i disagree|in my opinion|i believe|i think)\b/i', $intro) === 1;

        return $introWords < 20 || !$hasPosition;
    }

    private function hasWeakConclusion(string $responseText): bool
    {
        $paragraphs = preg_split('/\R{2,}/', trim($responseText));
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));
        $last = (string) (end($paragraphs) ?: '');
        if ($last === '') {
            return true;
        }

        $lastWords = count(array_filter(preg_split('/[^a-zA-Z\']+/', Str::lower($last))));
        $hasConclusionMarker = preg_match('/\b(in conclusion|to conclude|overall|to sum up)\b/i', $last) === 1;
        $hasPosition = preg_match('/\b(i agree|i disagree|should|must|cannot|can not)\b/i', $last) === 1;

        return $lastWords < 18 || (!$hasConclusionMarker && !$hasPosition);
    }

    private function extractStrengthsFromDiagnostic(array $diagnostic): array
    {
        $strengths = [];
        foreach ($diagnostic as $key => $block) {
            $percent = (int) ($block['percent'] ?? 0);
            if ($percent >= 70) {
                $strengths[] = match ($key) {
                    'task_response' => $this->localizedText("Savolga javob yo'nalishi to'g'ri.", "Task response direction is clear.", "Task response direction is clear."),
                    'coherence_cohesion' => $this->localizedText("Matn oqimi umumiy tushunarli.", "Overall text flow is understandable.", "Overall text flow is understandable."),
                    'lexical_resource' => $this->localizedText("Lug'at bazasi yomon emas.", "Vocabulary base is acceptable.", "Vocabulary base is acceptable."),
                    'grammar_accuracy' => $this->localizedText("Grammar nazorati asosiy darajada ushlangan.", "Grammar control is acceptable at core level.", "Grammar control is acceptable at core level."),
                    'sentence_variety' => $this->localizedText("Gap turlari ma'lum darajada xilma-xil.", "Sentence structures show some variation.", "Sentence structures show some variation."),
                    default => null,
                };
            }
        }

        return array_values(array_filter(array_unique($strengths)));
    }

    private function buildPracticeProgressSnapshot(WritingSubmission $submission): array
    {
        $current = is_array($submission->ai_feedback_json) ? $submission->ai_feedback_json : [];
        $currentMetrics = [
            'grammar_accuracy' => $this->extractDiagnosticPercent($current, 'grammar_accuracy'),
            'lexical_resource' => $this->extractDiagnosticPercent($current, 'lexical_resource'),
            'task_response' => $this->extractDiagnosticPercent($current, 'task_response'),
            'overall' => (int) max(0, min(100, (int) round((float) ($current['accuracy_percent'] ?? 0)))),
        ];

        $previous = WritingSubmission::query()
            ->where('user_id', $submission->user_id)
            ->where('id', '<', $submission->id)
            ->where('status', 'done')
            ->whereHas('task', fn ($query) => $query->where('mode', 'practice'))
            ->orderByDesc('id')
            ->first();

        if (!$previous || !is_array($previous->ai_feedback_json)) {
            return [
                'available' => false,
                'current' => $currentMetrics,
                'previous' => null,
                'delta' => null,
            ];
        }

        $prevPayload = $previous->ai_feedback_json;
        $previousMetrics = [
            'grammar_accuracy' => $this->extractDiagnosticPercent($prevPayload, 'grammar_accuracy'),
            'lexical_resource' => $this->extractDiagnosticPercent($prevPayload, 'lexical_resource'),
            'task_response' => $this->extractDiagnosticPercent($prevPayload, 'task_response'),
            'overall' => (int) max(0, min(100, (int) round((float) ($prevPayload['accuracy_percent'] ?? 0)))),
        ];

        return [
            'available' => true,
            'current' => $currentMetrics,
            'previous' => $previousMetrics,
            'delta' => [
                'grammar_accuracy' => $currentMetrics['grammar_accuracy'] - $previousMetrics['grammar_accuracy'],
                'lexical_resource' => $currentMetrics['lexical_resource'] - $previousMetrics['lexical_resource'],
                'task_response' => $currentMetrics['task_response'] - $previousMetrics['task_response'],
                'overall' => $currentMetrics['overall'] - $previousMetrics['overall'],
            ],
        ];
    }

    private function extractDiagnosticPercent(array $payload, string $key): int
    {
        $value = data_get($payload, "diagnostic.{$key}.percent");
        if (is_numeric($value)) {
            return (int) max(0, min(100, (int) round((float) $value)));
        }

        return match ($key) {
            'grammar_accuracy' => (int) max(0, min(100, (int) round((float) data_get($payload, 'accuracy_percent', 0)))),
            'lexical_resource' => (int) max(0, min(100, (int) round((float) data_get($payload, 'accuracy_percent', 0)))),
            'task_response' => (int) max(0, min(100, (int) round((float) data_get($payload, 'accuracy_percent', 0)))),
            default => 0,
        };
    }

    private function resolvePracticeAccuracy(WritingSubmission $submission): ?int
    {
        $payload = $submission->ai_feedback_json;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (is_array($payload)) {
            $direct = $this->normalizePercentValue(data_get($payload, 'accuracy_percent'));
            if ($direct !== null) {
                return $direct;
            }

            $health = $this->normalizePercentValue(data_get($payload, 'health_index.overall_percent'));
            if ($health !== null) {
                return $health;
            }

            $derived = $this->deriveAccuracyFromDiagnostic($payload);
            if ($derived !== null) {
                return $derived;
            }
        }

        if (is_numeric($submission->band_score)) {
            return max(0, min(100, (int) round((((float) $submission->band_score) / 9) * 100)));
        }

        return null;
    }

    private function normalizePercentValue($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (int) max(0, min(100, (int) round((float) $value)));
    }

    private function deriveAccuracyFromDiagnostic(array $payload): ?int
    {
        $keys = ['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_accuracy', 'sentence_variety'];
        $values = [];
        foreach ($keys as $key) {
            $percent = data_get($payload, "diagnostic.{$key}.percent");
            if (is_numeric($percent)) {
                $values[] = (float) $percent;
            }
        }

        if (empty($values)) {
            return null;
        }

        return (int) max(0, min(100, (int) round(array_sum($values) / count($values))));
    }

    private function buildPracticeFallbackLists(string $responseText, int $minWords, array $criteria): array
    {
        $wordCount = str_word_count($responseText);
        $strengths = [];
        $weaknesses = [];
        $improvements = [];

        $taskNote = (string) data_get($criteria, 'task_response.notes', '');
        $cohesionNote = (string) data_get($criteria, 'coherence_cohesion.notes', '');
        $lexicalNote = (string) data_get($criteria, 'lexical_resource.notes', '');
        $grammarNote = (string) data_get($criteria, 'grammar_range_accuracy.notes', '');

        if ($taskNote !== '' && !$this->isGenericFeedbackText($taskNote)) {
            $strengths[] = $taskNote;
        }
        if ($cohesionNote !== '' && !$this->isGenericFeedbackText($cohesionNote)) {
            $strengths[] = $cohesionNote;
        }
        if ($wordCount >= max(100, $minWords)) {
            $strengths[] = $this->localizedText(
                "Javob hajmi yetarli va fikrlar asosiy mavzu atrofida yozilgan.",
                "Obyom otveta dostatochnyi, i mysli derzhatsya v ramkah temi.",
                "Response length is sufficient and ideas stay on topic."
            );
        }

        if ($grammarNote !== '' && !$this->isGenericFeedbackText($grammarNote)) {
            $weaknesses[] = $grammarNote;
        }
        if ($lexicalNote !== '' && !$this->isGenericFeedbackText($lexicalNote)) {
            $weaknesses[] = $lexicalNote;
        }
        if ($wordCount < max(120, $minWords)) {
            $weaknesses[] = $this->localizedText(
                "Javob hajmini oshirish kerak: dalil va misollar yetarli emas.",
                "Nuzhno uvelichit' obyom: argumentov i primerov nedostatochno.",
                "Increase response length: supporting arguments and examples are not enough."
            );
        } else {
            $weaknesses[] = $this->localizedText(
                "Grammatik nazoratni kuchaytirish kerak: xatolarni alohida checklist bilan tekshirib yozing.",
                "Nuzhno usilit' grammaticheskii kontrol': proveriaite oshibki po chek-listu.",
                "Strengthen grammar control: check your errors against a simple checklist."
            );
        }

        $improvements[] = $this->localizedText(
            "Har paragraf uchun 1 asosiy fikr + 1 misol yozib, 10 daqiqada tekshiring.",
            "Dlya kazhdogo paragrafa napishite 1 osnovnuyu mysl' + 1 primer i prover'te za 10 minut.",
            "For each paragraph, write 1 main point + 1 example, then review it in 10 minutes."
        );
        $improvements[] = $this->localizedText(
            "Har kuni 15 daqiqa grammar drill qiling: fe'l zamonlari va subject-verb agreement.",
            "Kazhdyy den' 15 minut grammar-drill: vremena glagolov i subject-verb agreement.",
            "Do a 15-minute daily grammar drill: verb tenses and subject-verb agreement."
        );
        $improvements[] = $this->localizedText(
            "Yangi 10 ta topic vocabulary tanlab, 5 ta gapda ishlatib mashq qiling.",
            "Vyberite 10 novyh topic words i otrabotayte ih v 5 predlozheniyah.",
            "Pick 10 new topic words and practice them in 5 sentences."
        );

        return [
            'strengths' => $this->sanitizeStringList($strengths),
            'weaknesses' => $this->sanitizeStringList($weaknesses),
            'improvements' => $this->sanitizeStringList($improvements),
        ];
    }

    private function needsCriteriaFallback(array $criteria): bool
    {
        $notes = collect($criteria)
            ->pluck('notes')
            ->map(fn ($value) => $this->cleanAiText((string) $value))
            ->filter();

        if ($notes->isEmpty()) {
            return true;
        }

        $normalized = $notes->map(fn ($value) => $this->normalizeFeedbackKey($value))->filter();
        $uniqueNotes = $normalized->unique();
        if ($uniqueNotes->count() <= 1) {
            return true;
        }

        if ($normalized->count() >= 3 && $uniqueNotes->count() <= 2) {
            return true;
        }

        $normalizedValues = $normalized->values()->all();
        $count = count($normalizedValues);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->areSimilarFeedbackKeys($normalizedValues[$i], $normalizedValues[$j])) {
                    return true;
                }
            }
        }

        $avgLen = (int) round($notes->avg(fn ($value) => strlen((string) $value)));
        return $avgLen < 35;
    }

    private function buildFallbackCriteriaNotes(string $responseText, int $minWords, array $currentCriteria): array
    {
        $analysis = $this->analyzeWritingResponse($responseText, $minWords);
        $wordCount = $analysis['word_count'];
        $targetWords = $analysis['target_words'];
        $uniqueRatio = $analysis['unique_ratio'];
        $markerCount = $analysis['marker_count'];
        $grammarErrors = $analysis['grammar_errors'];
        $sample = $this->extractSamplePhrase($responseText);

        $taskNote = $this->localizedText(
            "Mavzuni ochgansiz, lekin javob hajmini {$targetWords}+ so'zga yetkazing va har paragrafga aniq misol qo'shing (hozir: {$wordCount} so'z). Namuna: \"{$sample}\".",
            "Tema yoritilgan, no uvelich'te obyom do {$targetWords}+ slov i dobav'te konkretnyy primer v kazhdyi paragraf (seichas: {$wordCount} slov). Primer: \"{$sample}\".",
            "You address the task, but extend to {$targetWords}+ words and add one concrete example per paragraph (now: {$wordCount} words). Example phrase: \"{$sample}\"."
        );
        $cohesionNote = $this->localizedText(
            "Bog'lovchilar soni {$markerCount} ta. Paragraflar orasida mantiqiy o'tishlarni kuchaytiring (because/however/therefore). Namuna: \"{$sample}\".",
            "Sv'yazok {$markerCount}. Usil'te logicheskie perehody mezhdu paragrafami (because/however/therefore). Primer: \"{$sample}\".",
            "You use {$markerCount} linkers. Strengthen transitions between paragraphs (because/however/therefore). Example phrase: \"{$sample}\"."
        );
        $lexicalNote = $this->localizedText(
            "Leksik xilma-xillikni oshiring: bir xil so'zlarni takrorlamang, har paragrafda 2-3 yangi topic so'z ishlating.",
            "Uvelich'te leksicheskoe raznoobrazie: men'she povtorov, v kazhdom paragrafe 2-3 novyh tematicheskih slova.",
            'Increase lexical variety: reduce repetition and use 2-3 new topic words in each paragraph.'
        );
        if ($uniqueRatio >= 0.58) {
            $lexicalNote = $this->localizedText(
                "Leksika yomon emas, keyingi qadam - collocation va aniq academic so'zlarni ko'paytirish.",
                "Leksika neplohaya, sleduyushchiy shag - dobavit' kolokatsii i bolee tochnye akademicheskie slova.",
                'Lexical range is acceptable; next step is adding stronger collocations and precise academic vocabulary.'
            );
        }

        $grammarNote = $this->localizedText(
            "Taxminan {$grammarErrors} ta grammatik naqsh xatosi ko'rindi. Ayniqsa subject-verb agreement va fe'l zamonlarini tekshiring. Namuna: \"{$sample}\".",
            "Obnaruzheno primerno {$grammarErrors} grammatikih oshibok po shablonam. Fokus na subject-verb agreement i vremena glagolov. Primer: \"{$sample}\".",
            "Around {$grammarErrors} grammar-pattern errors are detected. Focus on subject-verb agreement and verb tense control. Example phrase: \"{$sample}\"."
        );

        $criteria = [
            'task_response' => ['band' => null, 'notes' => $this->cleanAiText($taskNote)],
            'coherence_cohesion' => ['band' => null, 'notes' => $this->cleanAiText($cohesionNote)],
            'lexical_resource' => ['band' => null, 'notes' => $this->cleanAiText($lexicalNote)],
            'grammar_range_accuracy' => ['band' => null, 'notes' => $this->cleanAiText($grammarNote)],
        ];

        foreach ($criteria as $key => $item) {
            $currentNote = $this->cleanAiText((string) data_get($currentCriteria, $key.'.notes', ''));
            if ($currentNote !== '' && strlen($currentNote) >= 40 && !$this->isGenericFeedbackText($currentNote)) {
                $criteria[$key]['notes'] = $currentNote;
            }
        }

        return $criteria;
    }

    private function localizedText(string $uz, string $ru, string $en): string
    {
        return match (app()->getLocale()) {
            'uz' => $uz,
            'ru' => $ru,
            default => $en,
        };
    }

    private function isShallowFeedbackPayload(array $parsed): bool
    {
        $summaryRaw = $this->cleanAiText((string) ($parsed['summary'] ?? ''));
        $summary = Str::lower($summaryRaw);
        $criteriaNotes = collect($parsed['criteria'] ?? [])
            ->pluck('notes')
            ->map(fn ($value) => $this->cleanAiText((string) $value))
            ->filter()
            ->values();

        $allLists = collect([
            ...($parsed['strengths'] ?? []),
            ...($parsed['weaknesses'] ?? []),
            ...($parsed['improvements'] ?? []),
        ])->map(fn ($value) => $this->cleanAiText((string) $value))
            ->filter()
            ->values();

        $genericHits = 0;
        if ($this->isGenericFeedbackText($summaryRaw)) {
            $genericHits++;
        }
        foreach ($criteriaNotes as $note) {
            if ($this->isGenericFeedbackText($note)) {
                $genericHits++;
            }
        }
        foreach ($allLists as $item) {
            if ($this->isGenericFeedbackText($item)) {
                $genericHits++;
            }
        }

        $criteriaLower = $criteriaNotes->map(fn ($value) => $this->normalizeFeedbackKey($value))->filter();
        $listsLower = $allLists->map(fn ($value) => $this->normalizeFeedbackKey($value))->filter();
        $uniqueCriteria = $criteriaLower->unique()->count();
        $avgCriteriaLen = (int) round($criteriaNotes->avg(fn ($value) => strlen((string) $value)));
        $uniqueListItems = $listsLower->unique()->count();

        return $summary === ''
            || strlen($summary) < 40
            || $genericHits >= 2
            || $this->isLocaleMismatchText($summaryRaw)
            || $uniqueCriteria <= 2
            || $avgCriteriaLen < 40
            || $uniqueListItems <= 3;
    }

    private function applyHeuristicPracticeFeedback(array $parsed, string $responseText, int $minWords, array $analysis): array
    {
        $criteria = $this->buildFallbackCriteriaNotes($responseText, $minWords, []);
        $fallback = $this->buildPracticeFallbackLists($responseText, $minWords, $criteria);

        $summary = $this->localizedText(
            "Javobda asosiy pozitsiya bor, lekin grammatika va mantiqiy bog'lanishlarni kuchaytirish kerak. Eng katta foyda 3 yo'nalishda: grammatik aniqlik, paragraf o'tishlari va dalillarni kengaytirish.",
            "V otvete est' osnovnaya pozitsiya, no nuzhno usilit' grammatiku i logicheskie svyazki. Maksimal'nyi rost budet po 3 napravleniyam: grammaticheskaya tochnost', perehody mezhdu paragrafami i rasshirenie argumentov.",
            'Your response has a clear position, but grammar control and logical flow need work. The biggest gains are in three areas: grammatical accuracy, paragraph transitions, and fuller supporting arguments.'
        );

        $parsed['summary'] = $summary;
        $parsed['criteria'] = $criteria;
        $parsed['strengths'] = array_slice($fallback['strengths'], 0, 4);
        $parsed['weaknesses'] = array_slice($fallback['weaknesses'], 0, 4);
        $parsed['improvements'] = array_slice($fallback['improvements'], 0, 3);
        $baseAccuracy = (int) ($analysis['accuracy'] ?? ($parsed['accuracy_percent'] ?? 0));
        $parsed['accuracy_percent'] = max(20, min(82, $baseAccuracy));

        return $parsed;
    }

    private function extractSamplePhrase(string $responseText): string
    {
        $clean = preg_replace('/\s+/', ' ', trim($responseText));
        if (!$clean) {
            return 'sample phrase';
        }

        $parts = preg_split('/[.!?]/', $clean);
        $first = trim((string) ($parts[0] ?? $clean));
        $words = preg_split('/\s+/', $first);
        $words = array_slice(array_values(array_filter($words)), 0, 8);
        $sample = trim(implode(' ', $words));

        return $sample !== '' ? $sample : 'sample phrase';
    }

    private function isGenericFeedbackText(string $text): bool
    {
        $value = Str::lower($this->cleanAiText($text));
        if ($value === '') {
            return true;
        }

        $genericPhrases = [
            'muvaffaqiyatli vazifani',
            'juda kengdir',
            'aniq va ochiqdir',
            'sizning vazifangizda',
            'vazifani bajarishda',
            'ai fikri tayyor',
            'good job',
            'well done',
        ];

        foreach ($genericPhrases as $phrase) {
            if (str_contains($value, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function isLocaleMismatchText(string $text): bool
    {
        $value = Str::lower($this->cleanAiText($text));
        if ($value === '') {
            return false;
        }

        $locale = app()->getLocale();
        if ($locale === 'uz') {
            return preg_match('/\b(the|and|your|response|grammar|flow|need work|gains)\b/i', $value) === 1;
        }

        if ($locale === 'ru') {
            return preg_match('/\b(the|and|your|response|grammar|flow|need work|gains)\b/i', $value) === 1;
        }

        if ($locale === 'en') {
            return preg_match('/[а-яё]/iu', $value) === 1;
        }

        return false;
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
