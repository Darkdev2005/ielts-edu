<?php

namespace App\Http\Controllers;

use App\Models\AiRequest;
use App\Models\Question;
use App\Models\QuestionAIHelp;
use App\Exceptions\LimitExceededException;
use App\Services\FeatureGate;
use App\Services\UsageLimiter;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use Illuminate\Http\Request;

class QuestionAIHelpController extends Controller
{
    public function store(
        Request $request,
        Question $question,
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

        $help = QuestionAIHelp::create([
            'user_id' => $request->user()->id,
            'question_id' => $question->id,
            'status' => 'queued',
            'error_message' => null,
            'user_prompt' => $prompt,
            'ai_response' => null,
        ]);

        $task = 'question_help';
        $context = [
            'question' => $question->prompt,
            'options' => $question->options ?? [],
            'correct_answer' => $question->correct_answer,
            'user_language' => $help->user?->language ?: 'en',
        ];

        $userId = $request->user()->id;
        $allowed = $rateLimiter->hit('user', (string) $userId, (int) config('ai.user_rpm', 20));
        if (!$allowed) {
            $aiRequest = $service->logFailure(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 400],
                'User rate limit exceeded.',
            );
            $help->update([
                'status' => 'failed',
                'error_message' => 'User rate limit exceeded.',
                'ai_request_id' => $aiRequest->id,
            ]);
            return response()->json(['message' => 'User rate limit exceeded.'], 429);
        }

        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$globalAllowed) {
            $aiRequest = $service->logFailure(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 400],
                'Global rate limit exceeded.',
            );
            $help->update([
                'status' => 'failed',
                'error_message' => 'Global rate limit exceeded.',
                'ai_request_id' => $aiRequest->id,
            ]);
            return response()->json(['message' => 'Global rate limit exceeded.'], 429);
        }

        try {
            $aiRequest = $service->create(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 400]
            );
        } catch (LimitExceededException $e) {
            $message = __('app.daily_limit_reached');
            $aiRequest = $service->logFailure(
                $userId,
                $task,
                $prompt,
                $context,
                ['temperature' => 0.4, 'max_output_tokens' => 400],
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

    public function show(Request $request, QuestionAIHelp $help, FeatureGate $featureGate, UsageLimiter $usageLimiter)
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
