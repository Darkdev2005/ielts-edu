<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\AttemptAnswer;
use App\Services\AI\ExplanationGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class GenerateAnswerExplanationsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param int[] $answerIds
     */
    public function __construct(public array $answerIds)
    {
    }

    public function middleware(): array
    {
        return [new RateLimited('ai')];
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(ExplanationGenerator $generator): void
    {
        $answers = AttemptAnswer::with('question', 'attempt')
            ->whereIn('id', $this->answerIds)
            ->get()
            ->filter(fn ($answer) => $answer && !$answer->is_correct && !$answer->ai_explanation);

        if ($answers->isEmpty()) {
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $answers->first()?->attempt?->user_id,
            'job_type' => 'answer_explanations_batch',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'answer_ids='.implode(',', $answers->pluck('id')->all()),
            'meta' => [
                'answer_ids' => $answers->pluck('id')->all(),
            ],
            'started_at' => now(),
        ]);

        try {
            $explanations = $generator->generateBatch($answers->all());

            $updated = 0;
            foreach ($answers as $answer) {
                $text = trim((string) ($explanations[$answer->id] ?? ''));
                if ($text === '') {
                    continue;
                }
                $answer->ai_explanation = $text;
                $answer->save();
                $updated += 1;
            }

            $log->update([
                'status' => $updated > 0 ? 'success' : 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $updated > 0 ? null : 'No explanations generated.',
                'meta' => array_merge((array) ($log->meta ?? []), ['updated' => $updated]),
            ]);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $retryAfter = $this->extractRetryAfterSeconds($message);
            $isRateLimit = $this->isRateLimitError($message);

            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $message,
                'meta' => array_merge((array) ($log->meta ?? []), [
                    'error_code' => $e->getCode(),
                    'retry_after_seconds' => $retryAfter,
                    'rate_limited' => $isRateLimit,
                ]),
            ]);

            if ($isRateLimit) {
                $this->release($retryAfter ?? 60);
                return;
            }

            throw $e;
        }
    }

    private function isRateLimitError(string $message): bool
    {
        $message = strtolower($message);
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '429')
            || str_contains($message, 'quota exceeded')
            || str_contains($message, 'retry in');
    }

    private function extractRetryAfterSeconds(string $message): ?int
    {
        if (preg_match('/retry in ([0-9.]+)s/i', $message, $matches)) {
            return (int) ceil((float) $matches[1]) + 1;
        }

        return null;
    }
}
