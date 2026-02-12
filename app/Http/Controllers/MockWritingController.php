<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitExceededException;
use App\Models\AiRequest;
use App\Models\WritingSubmission;
use App\Models\WritingTask;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use App\Services\AI\WritingBandCalibrator;
use App\Services\FeatureGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MockWritingController extends Controller
{
    public function index(FeatureGate $featureGate)
    {
        $user = Auth::user();
        $canAccess = $this->canAccess($user, $featureGate);

        $tasks = WritingTask::query()
            ->where('is_active', true)
            ->where('mode', 'mock')
            ->orderByDesc('id')
            ->paginate(12);

        $recentSubmissions = collect();
        if ($user) {
            $recentSubmissions = WritingSubmission::query()
                ->where('user_id', $user->id)
                ->whereHas('task', fn ($query) => $query->where('mode', 'mock'))
                ->with('task')
                ->latest()
                ->limit(8)
                ->get();
        }

        return view('mock.writing.index', [
            'tasks' => $tasks,
            'canAccess' => $canAccess,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    public function show(WritingTask $task, FeatureGate $featureGate)
    {
        abort_if(!$task->is_active || $task->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            return redirect()
                ->route('mock.writing.index')
                ->with('status', __('app.upgrade_required'));
        }

        $latestSubmission = WritingSubmission::query()
            ->where('user_id', Auth::id())
            ->where('writing_task_id', $task->id)
            ->latest()
            ->first();

        return view('mock.writing.show', [
            'task' => $task,
            'latestSubmission' => $latestSubmission,
        ]);
    }

    public function submit(
        Request $request,
        WritingTask $task,
        FeatureGate $featureGate,
        RateLimiterMySql $rateLimiter,
        AiRequestService $service
    ) {
        abort_if(!$task->is_active || $task->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            return redirect()
                ->route('mock.writing.index')
                ->with('status', __('app.upgrade_required'));
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
            'user_id' => Auth::id(),
            'writing_task_id' => $task->id,
            'response_text' => $responseText,
            'word_count' => $wordCount,
            'status' => 'queued',
            'submitted_at' => now(),
        ]);

        $prompt = $this->buildMockWritingFeedbackPrompt($submission);
        $context = [
            'submission_id' => $submission->id,
            'task_type' => (string) $task->task_type,
            'task_prompt' => (string) $task->prompt,
            'response_text' => $responseText,
            'mode' => 'mock',
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

            return redirect()->route('mock.writing.result', $submission)
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

            return redirect()->route('mock.writing.result', $submission)
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

        return redirect()->route('mock.writing.result', $submission);
    }

    public function result(WritingSubmission $submission, FeatureGate $featureGate)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('task');
        abort_if(!$submission->task || $submission->task->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            return redirect()
                ->route('mock.writing.index')
                ->with('status', __('app.upgrade_required'));
        }

        $this->syncSubmissionAiStatus($submission);

        return view('mock.writing.result', [
            'submission' => $submission,
        ]);
    }

    public function status(WritingSubmission $submission, FeatureGate $featureGate)
    {
        if ($submission->user_id !== Auth::id()) {
            abort(403);
        }

        $submission->loadMissing('task');
        abort_if(!$submission->task || $submission->task->mode !== 'mock', 404);

        if (!$this->canAccess(Auth::user(), $featureGate)) {
            abort(403);
        }

        $this->syncSubmissionAiStatus($submission);

        return response()->json([
            'status' => $submission->status,
            'band_score' => $submission->band_score,
            'ai_feedback' => $submission->ai_feedback,
            'ai_feedback_json' => $submission->ai_feedback_json,
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

        if ($submission->status === 'done' && $submission->ai_feedback_json) {
            $this->calibrateStoredFeedbackIfNeeded($submission);
        }
    }

    private function applyWritingFeedbackFromRequest(WritingSubmission $submission, AiRequest $aiRequest): void
    {
        $text = (string) ($aiRequest->output_json['text'] ?? '');
        $parsed = $this->extractJson($text);
        $parsed = $this->normalizeStrictMockPayload($parsed, $text);
        $taskPrompt = (string) ($submission->task?->prompt ?? '');

        if ($this->isInvalidWritingFeedback($parsed, $text, $taskPrompt, (string) ($submission->task?->task_type ?? ''))) {
            if ($this->canRetryInvalidFeedback($submission)) {
                $this->retryWritingFeedback($submission, 'invalid_feedback');
                return;
            }
        }

        $parsed = app(WritingBandCalibrator::class)->calibrate(
            $parsed,
            (string) $submission->response_text,
            (string) ($submission->task?->task_type),
            (string) ($submission->task?->difficulty)
        );

        $submission->update([
            'status' => 'done',
            'band_score' => $parsed['overall_band'] ?? null,
            'ai_feedback' => $parsed['summary'] ?? ($text ?: null),
            'ai_feedback_json' => $parsed ?: null,
            'ai_error' => null,
            'completed_at' => now(),
        ]);
    }

    private function buildMockWritingFeedbackPrompt(WritingSubmission $submission): string
    {
        $task = $submission->task;
        $taskType = strtoupper((string) $task?->task_type);
        $taskPrompt = (string) ($task?->prompt ?? '');
        $responseText = (string) ($submission->response_text ?? '');
        $languageInstruction = $this->feedbackLanguageInstruction();
        if ($taskType === 'TASK1') {
            return "You are an IELTS Writing Task 1 official examiner (Academic data description).\n"
                ."Evaluate realistically and strictly using IELTS public band descriptors.\n"
                ."Never overestimate scores. If uncertain, choose the LOWER band.\n\n"
                ."ABSOLUTE RULES (TASK 1):\n"
                ."- Do NOT ask for opinions, reasons, or consequences.\n"
                ."- Do NOT give advice (should/must/need to).\n"
                ."- Do NOT include a conclusion (\"in conclusion\", \"to conclude\").\n"
                ."- Focus ONLY on overview, trends, comparisons, and accurate data language.\n"
                ."- Feedback must NOT encourage Task 2 style writing.\n\n"
                ."INPUT:\n"
                ."- task_type: {$taskType}\n"
                ."- task_question: {$taskPrompt}\n"
                ."- response: {$responseText}\n\n"
                ."MANDATORY EVALUATION CRITERIA:\n"
                ."1) Task Achievement (use JSON key task_response)\n"
                ."- Check if an overview is present.\n"
                ."- Check if key trends and comparisons are described.\n"
                ."- Check if data language is accurate.\n"
                ."- If no overview or no data/comparisons => task_response MUST NOT exceed 5.0.\n\n"
                ."2) Coherence & Cohesion\n"
                ."- Check logical paragraphing and progression.\n"
                ."- Check linking is natural, not mechanical.\n\n"
                ."3) Lexical Resource\n"
                ."- Check vocabulary range for data/trend description.\n"
                ."- Penalize repetition or vague words.\n\n"
                ."4) Grammar Range & Accuracy\n"
                ."- Check sentence variety and grammar control.\n"
                ."- If frequent errors affect clarity => reduce this criterion by at least 1.0.\n\n"
                ."SAFETY RULES:\n"
                ."- Word count alone NEVER increases band.\n"
                ."- Good structure does NOT compensate missing overview/data.\n"
                ."- If between two bands, choose LOWER.\n"
                ."- Never inflate scores to motivate.\n\n"
                ."LANGUAGE RULE:\n"
                ."- {$languageInstruction}\n\n"
                ."OUTPUT: Return ONLY valid JSON. No markdown. No extra text.\n"
                ."STRICT JSON SCHEMA:\n"
                ."{\n"
                ."  \"overall_band\": 0.0,\n"
                ."  \"confidence_level\": \"low|medium|high\",\n"
                ."  \"band_breakdown\": {\n"
                ."    \"task_response\": 0.0,\n"
                ."    \"coherence_cohesion\": 0.0,\n"
                ."    \"lexical_resource\": 0.0,\n"
                ."    \"grammar_accuracy\": 0.0\n"
                ."  },\n"
                ."  \"critical_feedback\": [\"string\", \"string\"],\n"
                ."  \"band_ceiling_reason\": \"string\",\n"
                ."  \"user_summary\": \"short and honest summary\"\n"
                ."}\n\n"
                ."FINAL CHECK:\n"
                ."- Use IELTS 0-9 bands with 0.5 steps.\n"
                ."- If Task 1 rules are violated (opinion/advice/conclusion), cap overall_band at 5.0.";
        }

        return "You are an IELTS Writing Task 2 official examiner.\n"
            ."Evaluate realistically and strictly using IELTS public band descriptors.\n"
            ."Never overestimate scores. If uncertain, choose the LOWER band.\n\n"
            ."INPUT:\n"
            ."- task_type: {$taskType}\n"
            ."- task_question: {$taskPrompt}\n"
            ."- essay: {$responseText}\n\n"
            ."MANDATORY EVALUATION CRITERIA:\n"
            ."1) Task Response (Idea Depth is CRITICAL)\n"
            ."- Check if position is clear.\n"
            ."- Check if ideas are analytical, not only descriptive.\n"
            ."- Check why/how/consequence development.\n"
            ."- Check examples are specific and explained.\n"
            ."- If ideas are generic, listed, repetitive, or predictable => task_response MUST NOT exceed 6.0.\n\n"
            ."2) Coherence & Cohesion\n"
            ."- Check logical paragraphing and progression.\n"
            ."- Check linking is natural, not mechanical.\n"
            ."- If mainly simple connectors only (First, Another, In conclusion) => max 6.0.\n\n"
            ."3) Lexical Resource\n"
            ."- Check vocabulary range, precision, and repetition.\n"
            ."- If mostly A2-B1 words (good, bad, very, many) => max 6.0.\n\n"
            ."4) Grammar Range & Accuracy\n"
            ."- Check sentence variety and grammar control.\n"
            ."- If frequent errors affect clarity => reduce this criterion by at least 1.0.\n\n"
            ."SHALLOW THINKING DETECTION (CRITICAL):\n"
            ."- If essay is descriptive not analytical, lacks cause-effect, uses unexplained examples, or avoids critical thinking:\n"
            ."  - You MUST include exact sentence: \"The ideas are shallow and descriptive rather than analytical.\"\n"
            ."  - overall_band MUST NOT exceed 6.0.\n\n"
            ."SAFETY RULES:\n"
            ."- Word count alone NEVER increases band.\n"
            ."- Good structure does NOT compensate weak ideas.\n"
            ."- If between two bands, choose LOWER.\n"
            ."- Never inflate scores to motivate.\n\n"
            ."LANGUAGE RULE:\n"
            ."- {$languageInstruction}\n\n"
            ."OUTPUT: Return ONLY valid JSON. No markdown. No extra text.\n"
            ."STRICT JSON SCHEMA:\n"
            ."{\n"
            ."  \"overall_band\": 0.0,\n"
            ."  \"confidence_level\": \"low|medium|high\",\n"
            ."  \"band_breakdown\": {\n"
            ."    \"task_response\": 0.0,\n"
            ."    \"coherence_cohesion\": 0.0,\n"
            ."    \"lexical_resource\": 0.0,\n"
            ."    \"grammar_accuracy\": 0.0\n"
            ."  },\n"
            ."  \"critical_feedback\": [\"string\", \"string\"],\n"
            ."  \"band_ceiling_reason\": \"string\",\n"
            ."  \"user_summary\": \"short and honest summary\"\n"
            ."}\n\n"
            ."FINAL CHECK:\n"
            ."- Use IELTS 0-9 bands with 0.5 steps.\n"
            ."- If shallow thinking detected, cap at 6.0 and include the exact required sentence in critical_feedback.";
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
                $summary = strtolower((string) ($parsed['summary'] ?? $parsed['user_summary'] ?? ''));
                foreach ($task1Forbidden as $phrase) {
                    if ($summary && str_contains($summary, $phrase)) {
                        return true;
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
        $prompt = $this->buildMockWritingFeedbackPrompt($submission);
        $context = [
            'submission_id' => $submission->id,
            'task_type' => (string) ($submission->task?->task_type),
            'task_prompt' => (string) ($submission->task?->prompt),
            'response_text' => (string) $submission->response_text,
            'retry_reason' => $reason,
            'mode' => 'mock',
        ];

        try {
            $aiRequest = app(AiRequestService::class)->create(
                $submission->user_id,
                'writing_feedback',
                $prompt,
                $context,
                ['temperature' => 0.2, 'max_output_tokens' => 1200],
                'mock_writing_feedback:'.$submission->id.':retry:'.$reason
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
        $fenceStripped = preg_replace('/\s*```$/', '', (string) $fenceStripped);
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

        $parsed = $this->extractJson($raw);
        if (!$parsed) {
            return;
        }
        $parsed = $this->normalizeStrictMockPayload($parsed, $raw);

        $submission->update([
            'ai_feedback' => $parsed['summary'] ?? ($raw ?: null),
            'ai_feedback_json' => $parsed,
            'band_score' => isset($parsed['overall_band']) && is_numeric($parsed['overall_band'])
                ? (float) $parsed['overall_band']
                : $submission->band_score,
        ]);
    }

    private function calibrateStoredFeedbackIfNeeded(WritingSubmission $submission): void
    {
        $raw = $submission->ai_feedback_json;
        $parsed = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($parsed)) {
            return;
        }

        $normalized = $this->normalizeStrictMockPayload($parsed, (string) $submission->ai_feedback);
        if (is_array($normalized)) {
            $parsed = $normalized;
        }

        $calibrated = app(WritingBandCalibrator::class)->calibrate(
            $parsed,
            (string) $submission->response_text,
            (string) ($submission->task?->task_type),
            (string) ($submission->task?->difficulty)
        );
        if (!is_array($calibrated)) {
            return;
        }

        $oldBand = isset($parsed['overall_band']) ? (float) $parsed['overall_band'] : null;
        $newBand = isset($calibrated['overall_band']) ? (float) $calibrated['overall_band'] : null;
        if ($newBand === null || $oldBand === $newBand) {
            if ($normalized !== null && $normalized !== $raw) {
                $submission->update([
                    'ai_feedback_json' => $parsed,
                ]);
            }
            return;
        }

        $submission->update([
            'band_score' => $newBand,
            'ai_feedback_json' => $calibrated,
        ]);
    }

    private function normalizeStrictMockPayload(?array $parsed, string $rawText = ''): ?array
    {
        if (!is_array($parsed)) {
            return $parsed;
        }

        $overallBand = $this->normalizeBandValue($parsed['overall_band'] ?? null);
        $confidence = strtolower((string) ($parsed['confidence_level'] ?? 'medium'));
        if (!in_array($confidence, ['low', 'medium', 'high'], true)) {
            $confidence = 'medium';
        }

        $bandBreakdown = $parsed['band_breakdown'] ?? [];
        if (!is_array($bandBreakdown)) {
            $bandBreakdown = [];
        }

        $taskBand = $this->normalizeBandValue($bandBreakdown['task_response'] ?? data_get($parsed, 'criteria.task_response.band'));
        $coherenceBand = $this->normalizeBandValue($bandBreakdown['coherence_cohesion'] ?? data_get($parsed, 'criteria.coherence_cohesion.band'));
        $lexicalBand = $this->normalizeBandValue($bandBreakdown['lexical_resource'] ?? data_get($parsed, 'criteria.lexical_resource.band'));
        $grammarBand = $this->normalizeBandValue($bandBreakdown['grammar_accuracy'] ?? data_get($parsed, 'criteria.grammar_range_accuracy.band'));

        $criticalFeedback = $parsed['critical_feedback'] ?? [];
        if (!is_array($criticalFeedback)) {
            $criticalFeedback = [];
        }
        $criticalFeedback = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $criticalFeedback
        )));

        $ceilingReason = trim((string) ($parsed['band_ceiling_reason'] ?? ''));
        if ($ceilingReason === '' && !empty($criticalFeedback)) {
            $ceilingReason = (string) ($criticalFeedback[0] ?? '');
        }

        $summary = trim((string) ($parsed['user_summary'] ?? $parsed['summary'] ?? ''));
        if ($summary === '') {
            $summary = $ceilingReason !== '' ? $ceilingReason : trim($rawText);
        }

        $criteria = [
            'task_response' => [
                'band' => $taskBand,
                'notes' => (string) ($this->findFeedbackByKeyword($criticalFeedback, ['idea', 'argument', 'analysis', 'task']) ?? ''),
            ],
            'coherence_cohesion' => [
                'band' => $coherenceBand,
                'notes' => (string) ($this->findFeedbackByKeyword($criticalFeedback, ['coherence', 'cohesion', 'paragraph', 'link']) ?? ''),
            ],
            'lexical_resource' => [
                'band' => $lexicalBand,
                'notes' => (string) ($this->findFeedbackByKeyword($criticalFeedback, ['lexical', 'vocab', 'word']) ?? ''),
            ],
            'grammar_range_accuracy' => [
                'band' => $grammarBand,
                'notes' => (string) ($this->findFeedbackByKeyword($criticalFeedback, ['grammar', 'tense', 'article', 'agreement', 'clarity']) ?? ''),
            ],
        ];

        foreach ($criteria as $key => $criterion) {
            if ($criterion['notes'] !== '') {
                continue;
            }

            $fallbackNote = data_get($parsed, "criteria.{$key}.notes");
            $criteria[$key]['notes'] = is_string($fallbackNote) ? trim($fallbackNote) : '';
        }

        if ($overallBand === null) {
            $bands = array_values(array_filter([
                $taskBand,
                $coherenceBand,
                $lexicalBand,
                $grammarBand,
            ], fn ($band) => is_numeric($band)));
            if (!empty($bands)) {
                $overallBand = round((array_sum($bands) / count($bands)) * 2) / 2;
            }
        }

        if ($this->hasShallowThinkingFlag($criticalFeedback) && $overallBand !== null) {
            $overallBand = min($overallBand, 6.0);
        }

        if (!isset($parsed['strengths']) || !is_array($parsed['strengths'])) {
            $parsed['strengths'] = [];
        }
        if (!isset($parsed['weaknesses']) || !is_array($parsed['weaknesses'])) {
            $parsed['weaknesses'] = $criticalFeedback;
        }
        if (!isset($parsed['improvements']) || !is_array($parsed['improvements'])) {
            $parsed['improvements'] = [];
        }

        $parsed['overall_band'] = $overallBand;
        $parsed['confidence_level'] = $confidence;
        $parsed['band_breakdown'] = [
            'task_response' => $taskBand,
            'coherence_cohesion' => $coherenceBand,
            'lexical_resource' => $lexicalBand,
            'grammar_accuracy' => $grammarBand,
        ];
        $parsed['criteria'] = $criteria;
        $parsed['critical_feedback'] = $criticalFeedback;
        $parsed['band_ceiling_reason'] = $ceilingReason;
        $parsed['summary'] = $summary;
        $parsed['user_summary'] = $summary;
        $parsed['weaknesses'] = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $parsed['weaknesses']
        )));

        return $parsed;
    }

    private function normalizeBandValue($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $band = (float) $value;
        $band = max(0.0, min(9.0, $band));
        return round($band * 2) / 2;
    }

    private function findFeedbackByKeyword(array $feedbackItems, array $keywords): ?string
    {
        foreach ($feedbackItems as $item) {
            $lower = Str::lower((string) $item);
            foreach ($keywords as $keyword) {
                if (str_contains($lower, Str::lower((string) $keyword))) {
                    return (string) $item;
                }
            }
        }

        return $feedbackItems[0] ?? null;
    }

    private function hasShallowThinkingFlag(array $feedbackItems): bool
    {
        foreach ($feedbackItems as $item) {
            $text = Str::lower((string) $item);
            if (str_contains($text, 'shallow and descriptive rather than analytical')) {
                return true;
            }
        }

        return false;
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
