<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiRequest;
use App\Http\Resources\AiRequestResource;
use App\Models\AiRequest;
use App\Exceptions\LimitExceededException;
use App\Services\AI\AiRequestService;
use App\Services\AI\RateLimiterMySql;
use Illuminate\Http\JsonResponse;

class AiRequestController extends Controller
{
    public function store(StoreAiRequest $request, RateLimiterMySql $rateLimiter, AiRequestService $service): JsonResponse
    {
        $userId = $request->user()?->id;
        $task = $request->input('task');
        $prompt = $request->input('prompt');
        $context = $request->input('context', []);
        $parameters = $request->input('parameters', []);
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($userId) {
            $allowed = $rateLimiter->hit('user', (string) $userId, (int) config('ai.user_rpm', 20));
            if (!$allowed) {
                return response()->json(['message' => 'User rate limit exceeded.'], 429);
            }
        }

        $globalAllowed = $rateLimiter->hit('global', 'global', (int) config('ai.global_rpm', 200));
        if (!$globalAllowed) {
            return response()->json(['message' => 'Global rate limit exceeded.'], 429);
        }

        try {
            $aiRequest = $service->create($userId, $task, $prompt, $context, $parameters, $idempotencyKey);
        } catch (LimitExceededException $e) {
            return response()->json(['message' => __('app.ai_demo_limit_reached')], 429);
        }

        return (new AiRequestResource($aiRequest))->response()->setStatusCode($aiRequest->status === 'done' ? 200 : 202);
    }

    public function show(string $id): JsonResponse
    {
        $aiRequest = AiRequest::findOrFail($id);
        return (new AiRequestResource($aiRequest))->response();
    }

}
