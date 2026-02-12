<?php

namespace App\Jobs;

use App\Models\AiCache;
use App\Models\AiProviderState;
use App\Models\AiRequest;
use App\Services\AI\GeminiClient;
use App\Services\AI\GeminiException;
use App\Services\AI\GroqClient;
use App\Services\AI\GroqException;
use App\Services\AI\LockManagerMySql;
use App\Services\AI\WritingBandCalibrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAiRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public string $requestId)
    {
    }

    public function handle(GeminiClient $geminiClient, GroqClient $groqClient, LockManagerMySql $lockManager): void
    {
        $aiRequest = AiRequest::find($this->requestId);
        if (!$aiRequest || in_array($aiRequest->status, ['done', 'failed', 'failed_quota'], true)) {
            return;
        }

        $provider = $aiRequest->provider ?: config('ai.provider', 'gemini');
        $cooldownSeconds = $this->checkProviderCooldown($provider);
        if ($cooldownSeconds > 0) {
            $this->release($cooldownSeconds);
            return;
        }

        $maxConcurrency = (int) config('ai.max_concurrency', 5);
        $slot = $lockManager->acquire($provider, $maxConcurrency);
        if (!$slot) {
            $this->release(5);
            return;
        }

        try {
            $task = (string) data_get($aiRequest->input_json, 'task', 'generic');
            $dedupEnabled = $this->isDedupEnabledForTask($task);

            if ($dedupEnabled) {
                $cache = AiCache::where('dedup_hash', $aiRequest->dedup_hash)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($cache) {
                    $aiRequest->update([
                        'status' => 'done',
                        'output_json' => $cache->response_json,
                        'started_at' => $aiRequest->started_at ?: now(),
                        'finished_at' => now(),
                    ]);
                    return;
                }
            }

            AiRequest::where('id', $aiRequest->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'started_at' => now(),
                ]);

            $input = $aiRequest->input_json ?? [];
            $parameters = (array) ($input['parameters'] ?? []);
            $parameters['task'] = $input['task'] ?? 'generic';

            $client = $provider === 'groq' ? $groqClient : $geminiClient;
            $result = $client->generate(
                (string) ($input['prompt'] ?? ''),
                (array) ($input['context'] ?? []),
                $parameters
            );

            $output = [
                'text' => $result['text'] ?? '',
                'raw' => $result['raw'] ?? [],
            ];

            $aiRequest->update([
                'status' => 'done',
                'output_json' => $output,
                'provider' => $provider,
                'model' => $result['model'] ?? $aiRequest->model,
                'prompt_tokens' => $result['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $result['usage']['output_tokens'] ?? null,
                'finished_at' => now(),
            ]);

            if ($dedupEnabled) {
                $ttl = (int) config('ai.dedup_ttl_minutes', 60);
                AiCache::updateOrCreate(
                    ['dedup_hash' => $aiRequest->dedup_hash],
                    [
                        'response_json' => $output,
                        'expires_at' => now()->addMinutes($ttl),
                    ]
                );
            }

            $this->syncLinkedRecords($aiRequest, $output['text'] ?? '');
        } catch (GeminiException|GroqException $e) {
            $message = $e->getMessage();
            $status = $e->statusCode;
            $isQuota = $this->isQuotaExceeded($message);
            $retryAfter = $this->extractRetryAfterSeconds($message);

            if ($status === 429 && $isQuota) {
                if ($this->attempts() >= $this->tries) {
                    $aiRequest->update([
                        'status' => 'failed_quota',
                        'error_text' => $message,
                        'finished_at' => now(),
                    ]);
                    $this->syncLinkedFailure($aiRequest, $message);
                    return;
                }

                $cooldown = max($retryAfter, (int) config('ai.provider_cooldown_seconds', 60));
                $this->setProviderCooldown($provider, $cooldown);
                $aiRequest->update([
                    'status' => 'pending',
                    'error_text' => $message,
                    'started_at' => null,
                ]);
                $this->release($cooldown + 5);
                return;
            }

            if ($status === 429 || $status >= 500) {
                if ($status === 429 && $retryAfter > 0) {
                    $this->setProviderCooldown($provider, max($retryAfter, 30));
                }
                $aiRequest->update([
                    'status' => 'pending',
                    'error_text' => $message,
                    'started_at' => null,
                ]);
                $this->release($this->backoffSeconds());
                return;
            }

            $aiRequest->update([
                'status' => 'failed',
                'error_text' => $message,
                'finished_at' => now(),
            ]);

            $this->syncLinkedFailure($aiRequest, $message);
        } catch (\Throwable $e) {
            $aiRequest->update([
                'status' => 'failed',
                'error_text' => (string) $e->getMessage(),
                'finished_at' => now(),
            ]);

            $this->syncLinkedFailure($aiRequest, (string) $e->getMessage());
        } finally {
            $lockManager->release($slot);
        }
    }

    public function failed(\Throwable $e): void
    {
        $aiRequest = AiRequest::find($this->requestId);
        if (!$aiRequest || in_array($aiRequest->status, ['done', 'failed', 'failed_quota'], true)) {
            return;
        }

        $aiRequest->update([
            'status' => 'failed',
            'error_text' => (string) $e->getMessage(),
            'finished_at' => now(),
        ]);
    }

    private function backoffSeconds(): int
    {
        $attempt = max(1, $this->attempts());
        $base = 5;
        $delay = $base * (2 ** ($attempt - 1));
        return min($delay, 60);
    }

    private function isQuotaExceeded(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, 'quota exceeded')
            || str_contains($message, 'billing')
            || str_contains($message, 'plan')
            || str_contains($message, 'generate_content_free_tier_requests');
    }

    private function extractRetryAfterSeconds(string $message): int
    {
        if (preg_match('/retry\\s+in\\s+([0-9.]+)s/i', $message, $matches)) {
            $seconds = (int) ceil((float) $matches[1]);
            if ($seconds <= 0) {
                return 0;
            }
            return min(max($seconds, 10), 180);
        }

        return 0;
    }

    private function isDedupEnabledForTask(string $task): bool
    {
        $excludedTasks = (array) config('ai.dedup_exclude_tasks', ['writing_feedback', 'speaking_feedback', 'writing_followup']);

        return !in_array($task, $excludedTasks, true);
    }

    private function checkProviderCooldown(string $provider): int
    {
        $state = AiProviderState::where('provider', $provider)->first();
        if (!$state || !$state->cooldown_until) {
            return 0;
        }

        $now = now();
        if ($state->cooldown_until->lte($now)) {
            return 0;
        }

        $remaining = $now->diffInSeconds($state->cooldown_until);

        return min($remaining + 5, 300);
    }

    private function setProviderCooldown(string $provider, int $seconds): void
    {
        $seconds = max(10, $seconds);
        AiProviderState::updateOrCreate(
            ['provider' => $provider],
            ['cooldown_until' => now()->addSeconds($seconds)]
        );
    }

    private function syncLinkedRecords(AiRequest $aiRequest, string $text): void
    {
        $task = $aiRequest->input_json['task'] ?? null;
        if (!$task) {
            return;
        }

        if ($task === 'question_help') {
            \DB::table('question_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'done',
                    'ai_response' => $text,
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'grammar_exercise_help') {
            \DB::table('grammar_exercise_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'done',
                    'ai_response' => $text,
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'grammar_topic_help') {
            \DB::table('grammar_topic_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'done',
                    'ai_response' => $text,
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'writing_followup') {
            \DB::table('writing_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'done',
                    'ai_response' => $text,
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'writing_feedback') {
            $parsed = $this->extractJson($text);
            $submissionRow = \DB::table('writing_submissions')
                ->where('ai_request_id', $aiRequest->id)
                ->first(['id', 'response_text', 'writing_task_id']);
            $taskRow = null;
            if ($submissionRow) {
                $taskRow = \DB::table('writing_tasks')
                    ->where('id', $submissionRow->writing_task_id)
                    ->first(['task_type', 'difficulty']);
            }
            if ($submissionRow && $parsed) {
                $parsed = app(WritingBandCalibrator::class)->calibrate(
                    $parsed,
                    (string) $submissionRow->response_text,
                    (string) ($taskRow?->task_type ?? null),
                    (string) ($taskRow?->difficulty ?? null)
                );
            }
            \DB::table('writing_submissions')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'done',
                    'band_score' => $parsed['overall_band'] ?? null,
                    'ai_feedback' => $parsed['summary'] ?? ($text ?: null),
                    'ai_feedback_json' => $parsed ? json_encode($parsed) : null,
                    'ai_error' => null,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'speaking_feedback') {
            $parsed = $this->extractJson($text);
            \DB::table('speaking_submissions')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'done',
                    'band_score' => $parsed['overall_band'] ?? null,
                    'ai_feedback' => $parsed['summary'] ?? ($text ?: null),
                    'ai_feedback_json' => $parsed ? json_encode($parsed) : null,
                    'ai_error' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncLinkedFailure(AiRequest $aiRequest, string $message): void
    {
        $task = $aiRequest->input_json['task'] ?? null;
        if (!$task) {
            return;
        }

        if ($task === 'question_help') {
            \DB::table('question_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'grammar_exercise_help') {
            \DB::table('grammar_exercise_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'grammar_topic_help') {
            \DB::table('grammar_topic_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'writing_followup') {
            \DB::table('writing_ai_helps')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'failed',
                    'error_message' => $message,
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'writing_feedback') {
            \DB::table('writing_submissions')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'failed',
                    'ai_error' => $message,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            return;
        }

        if ($task === 'speaking_feedback') {
            \DB::table('speaking_submissions')
                ->where('ai_request_id', $aiRequest->id)
                ->update([
                    'status' => 'failed',
                    'ai_error' => $message,
                    'updated_at' => now(),
                ]);
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
}
