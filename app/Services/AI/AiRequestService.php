<?php

namespace App\Services\AI;

use App\Exceptions\LimitExceededException;
use App\Jobs\ProcessAiRequestJob;
use App\Models\AiCache;
use App\Models\AiRequest;
use App\Models\User;
use App\Services\FeatureGate;
use App\Services\UsageLimiter;
use Illuminate\Support\Facades\DB;

class AiRequestService
{
    public function create(
        ?int $userId,
        string $task,
        string $prompt,
        array $context = [],
        array $parameters = [],
        ?string $idempotencyKey = null,
    ): AiRequest {
        $provider = $this->resolveProvider($task);
        $model = $provider === 'groq'
            ? config('ai.groq.model', 'llama-3.1-8b-instant')
            : config('ai.gemini.model', 'gemini-2.5-flash');

        if ($idempotencyKey) {
            $existing = AiRequest::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $user = null;
        $isFreePlan = false;
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $plan = app(FeatureGate::class)->currentPlan($user);
                $isFreePlan = $plan?->slug === 'free';
            }
        }

        if ($isFreePlan) {
            $freeMaxTokens = (int) config('ai.free_max_output_tokens', 150);
            $freeWritingMax = (int) config('ai.free_writing_max_output_tokens', 800);
            $freeSpeakingMax = (int) config('ai.free_speaking_max_output_tokens', 600);
            $requestedMax = (int) ($parameters['max_output_tokens'] ?? $freeMaxTokens);
            if ($task === 'writing_feedback') {
                $cap = $freeWritingMax;
            } elseif ($task === 'speaking_feedback') {
                $cap = $freeSpeakingMax;
            } else {
                $cap = $freeMaxTokens;
            }
            $parameters['max_output_tokens'] = min($requestedMax, $cap);
        }

        $dedupHash = $this->computeDedupHash($provider, $model, $task, $prompt, $context, $parameters);
        $dedupEnabled = $this->isDedupEnabledForTask($task);

        if ($dedupEnabled) {
            $cache = AiCache::where('dedup_hash', $dedupHash)
                ->where('expires_at', '>', now())
                ->first();

            if ($cache) {
                return AiRequest::create([
                    'user_id' => $userId,
                    'status' => 'done',
                    'input_json' => $this->buildInputJson($task, $prompt, $context, $parameters),
                    'output_json' => $cache->response_json,
                    'dedup_hash' => $dedupHash,
                    'idempotency_key' => $idempotencyKey,
                    'provider' => $provider,
                    'model' => $model,
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }
        }

        if ($isFreePlan && $user) {
            app(UsageLimiter::class)->assertWithinLimit($user, 'ai_daily');
        }

        try {
            $aiRequest = DB::transaction(function () use ($userId, $dedupHash, $idempotencyKey, $task, $prompt, $context, $parameters, $model, $provider) {
                return AiRequest::create([
                    'user_id' => $userId,
                    'status' => 'pending',
                    'input_json' => $this->buildInputJson($task, $prompt, $context, $parameters),
                    'dedup_hash' => $dedupHash,
                    'idempotency_key' => $idempotencyKey,
                    'provider' => $provider,
                    'model' => $model,
                ]);
            });
        } catch (\Throwable $e) {
            if ($idempotencyKey) {
                $existing = AiRequest::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }
            throw $e;
        }

        if ($isFreePlan && $user) {
            app(UsageLimiter::class)->increment($user, 'ai_daily');
        }

        ProcessAiRequestJob::dispatch($aiRequest->id);

        return $aiRequest;
    }

    public function logFailure(
        ?int $userId,
        string $task,
        string $prompt,
        array $context,
        array $parameters,
        string $message,
        string $status = 'failed',
        ?string $idempotencyKey = null,
    ): AiRequest {
        $provider = $this->resolveProvider($task);
        $model = $provider === 'groq'
            ? config('ai.groq.model', 'llama-3.1-8b-instant')
            : config('ai.gemini.model', 'gemini-2.5-flash');
        $dedupHash = $this->computeDedupHash($provider, $model, $task, $prompt, $context, $parameters);

        return AiRequest::create([
            'user_id' => $userId,
            'status' => $status,
            'input_json' => $this->buildInputJson($task, $prompt, $context, $parameters),
            'output_json' => null,
            'error_text' => $message,
            'dedup_hash' => $dedupHash,
            'idempotency_key' => $idempotencyKey,
            'provider' => $provider,
            'model' => $model,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function buildInputJson(string $task, string $prompt, array $context, array $parameters): array
    {
        return [
            'task' => $task,
            'prompt' => $prompt,
            'context' => $context,
            'parameters' => $parameters,
        ];
    }

    private function computeDedupHash(string $provider, string $model, string $task, string $prompt, array $context, array $parameters): string
    {
        $payload = [
            'provider' => $provider,
            'model' => $model,
            'task' => $task,
            'prompt' => $prompt,
            'context' => $this->normalize($context),
            'parameters' => $this->normalize($parameters),
        ];

        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalize(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value);
            }
        }
        return $data;
    }

    private function resolveProvider(string $task): string
    {
        $taskProvider = config("ai.task_providers.{$task}");
        if (is_string($taskProvider) && $taskProvider !== '') {
            $taskProvider = strtolower(trim($taskProvider));
            if (in_array($taskProvider, ['gemini', 'groq'], true)) {
                return $taskProvider;
            }
        }

        return config('ai.provider', 'gemini');
    }

    private function isDedupEnabledForTask(string $task): bool
    {
        $excludedTasks = (array) config('ai.dedup_exclude_tasks', ['writing_feedback', 'speaking_feedback', 'writing_followup']);

        return !in_array($task, $excludedTasks, true);
    }
}
