<?php

namespace App\Http\Controllers;

use App\Models\AiRequest;
use App\Models\GrammarExercise;
use App\Models\GrammarExerciseAIHelp;
use App\Exceptions\LimitExceededException;
use App\Services\FeatureGate;
use App\Services\UsageLimiter;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use Illuminate\Http\Request;

class GrammarExerciseAIHelpController extends Controller
{
    public function store(
        Request $request,
        GrammarExercise $exercise,
        RateLimiterMySql $rateLimiter,
        AiRequestService $service,
        FeatureGate $featureGate,
        UsageLimiter $usageLimiter
    )
    {
        $validated = $request->validate([
            'prompt' => ['nullable', 'string', 'max:500'],
        ]);

        $prompt = trim($validated['prompt'] ?? '');
        if ($prompt === '') {
            return response()->json([
                'message' => 'Prompt is required.',
            ], 422);
        }

        $help = GrammarExerciseAIHelp::firstOrCreate([
            'user_id' => $request->user()->id,
            'grammar_exercise_id' => $exercise->id,
        ]);

        if (in_array($help->status, ['queued', 'processing'], true) && $help->ai_request_id) {
            return response()->json([
                'id' => $help->id,
                'status' => $help->status,
            ], 202);
        }

        $help->update([
            'status' => 'queued',
            'error_message' => null,
            'user_prompt' => $prompt,
            'ai_response' => null,
        ]);

        $task = 'grammar_exercise_help';
        $context = [
            'exercise' => $exercise->question ?? $exercise->prompt,
            'options' => $exercise->options ?? [],
            'correct_answer' => $exercise->correct_answer,
            'user_language' => $help->user?->language ?: 'en',
        ];

        $userId = $request->user()->id;
        $allowed = $rateLimiter->hit('user', (string) $userId, (int) config('ai.user_rpm', 20));
        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$allowed || !$globalAllowed) {
            $message = !$allowed ? 'User rate limit exceeded.' : 'Global rate limit exceeded.';
            $aiRequest = $service->logFailure(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 300],
                $message,
            );
            $help->update([
                'status' => 'failed',
                'error_message' => $message,
                'ai_request_id' => $aiRequest->id,
            ]);
            return response()->json(['message' => 'Rate limit exceeded.'], 429);
        }

        try {
            $aiRequest = $service->create(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 300]
            );
        } catch (LimitExceededException $e) {
            $message = __('app.daily_limit_reached');
            $aiRequest = $service->logFailure(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 300],
                $message,
            );
            $help->update([
                'status' => 'failed',
                'error_message' => $message,
                'ai_request_id' => $aiRequest->id,
            ]);
            return response()->json(['message' => $message, 'upgrade_prompt' => true], 429);
        }

        $help->update([
            'ai_request_id' => $aiRequest->id,
            'status' => $aiRequest->status === 'done' ? 'done' : 'queued',
            'ai_response' => $aiRequest->output_json['text'] ?? null,
            'error_message' => $aiRequest->error_text,
        ]);

        $limitNotice = null;
        if ($request->user()) {
            $plan = $featureGate->currentPlan($request->user());
            if ($plan?->slug === 'free') {
                $remaining = $usageLimiter->remaining($request->user(), 'ai_daily');
                if ($remaining === 0) {
                    $limitNotice = __('app.daily_limit_reached');
                }
            }
        }

        return response()->json([
            'id' => $help->id,
            'status' => $help->status,
            'limit_notice' => $limitNotice,
        ]);
    }

    public function show(Request $request, GrammarExerciseAIHelp $help, FeatureGate $featureGate, UsageLimiter $usageLimiter)
    {
        if ($help->user_id !== $request->user()->id) {
            abort(403);
        }

        if (in_array($help->status, ['queued', 'processing'], true) && $help->ai_request_id) {
            $aiRequest = AiRequest::find($help->ai_request_id);
            if ($aiRequest) {
                if ($aiRequest->isStuckPending((int) config('ai.max_pending_seconds', 120))) {
                    $aiRequest->update([
                        'status' => 'failed',
                        'error_text' => __('app.ai_help_failed'),
                        'finished_at' => now(),
                    ]);
                    $help->update([
                        'status' => 'failed',
                        'error_message' => __('app.ai_help_failed'),
                    ]);
                } elseif ($aiRequest->status === 'pending' && $aiRequest->isQuotaError()) {
                    $aiRequest->update([
                        'status' => 'failed_quota',
                        'finished_at' => now(),
                    ]);
                    $help->update([
                        'status' => 'failed',
                        'error_message' => $aiRequest->error_text,
                    ]);
                } elseif ($aiRequest->status === 'processing' && $aiRequest->started_at) {
                    $maxPending = (int) config('ai.max_pending_seconds', 120);
                    if ($maxPending > 0 && $aiRequest->started_at->diffInSeconds(now()) > $maxPending) {
                        $aiRequest->update([
                            'status' => 'failed',
                            'error_text' => __('app.ai_help_failed'),
                            'finished_at' => now(),
                        ]);
                        $help->update([
                            'status' => 'failed',
                            'error_message' => __('app.ai_help_failed'),
                        ]);
                    }
                }

                if ($aiRequest->status === 'done') {
                    $help->update([
                        'status' => 'done',
                        'ai_response' => $aiRequest->output_json['text'] ?? null,
                        'error_message' => null,
                    ]);
                } elseif (in_array($aiRequest->status, ['failed', 'failed_quota'], true)) {
                    $help->update([
                        'status' => 'failed',
                        'error_message' => $aiRequest->error_text,
                    ]);
                } elseif ($aiRequest->status === 'processing') {
                    $help->update(['status' => 'processing']);
                }
            }
        }

        $limitNotice = null;
        if ($request->user()) {
            $plan = $featureGate->currentPlan($request->user());
            if ($plan?->slug === 'free') {
                $remaining = $usageLimiter->remaining($request->user(), 'ai_daily');
                if ($remaining === 0) {
                    $limitNotice = __('app.daily_limit_reached');
                }
            }
        }

        return response()->json([
            'id' => $help->id,
            'status' => $help->status,
            'ai_response' => $help->ai_response,
            'error_message' => $help->error_message,
            'limit_notice' => $limitNotice,
        ]);
    }
}
