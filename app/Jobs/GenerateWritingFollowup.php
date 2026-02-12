<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\WritingAIHelp;
use App\Services\AI\AIClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWritingFollowup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $helpId)
    {
    }

    public function handle(AIClient $client): void
    {
        $help = WritingAIHelp::with('submission.task')->find($this->helpId);
        if (!$help || $help->status === 'done') {
            return;
        }

        $submission = $help->submission;
        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $help->user_id,
            'job_type' => 'writing_followup',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'writing_help_id='.$help->id.'; submission_id='.$submission?->id,
            'meta' => [
                'writing_help_id' => $help->id,
                'submission_id' => $submission?->id,
            ],
            'started_at' => now(),
        ]);

        try {
            $help->update(['status' => 'running']);

            $criteria = $submission?->ai_feedback_json['criteria'] ?? [];
            $summary = $submission?->ai_feedback_json['summary'] ?? $submission?->ai_feedback ?? '';

            $history = WritingAIHelp::where('writing_submission_id', $submission->id)
                ->where('id', '!=', $help->id)
                ->orderByDesc('id')
                ->limit(3)
                ->get()
                ->reverse();

            $historyText = '';
            foreach ($history as $item) {
                $historyText .= "User: {$item->user_prompt}\nAssistant: {$item->ai_response}\n";
            }

            $system = "You are an IELTS writing tutor. Help the student fix mistakes and explain clearly. Keep answers concise and practical.";

            $prompt = "Task: ".($submission?->task?->title ?? 'Writing Task')."\n"
                ."Prompt: ".($submission?->task?->prompt ?? '')."\n\n"
                ."Student response:\n".($submission?->response_text ?? '')."\n\n"
                ."AI summary:\n".$summary."\n\n"
                ."Criteria feedback:\n".json_encode($criteria, JSON_UNESCAPED_UNICODE)."\n\n"
                .($historyText ? "Chat history:\n".$historyText."\n" : '')
                ."User question: ".$help->user_prompt."\n\n"
                ."Answer in the same language as the user prompt.";

            $response = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ]);

            $text = $response['choices'][0]['message']['content'] ?? '';
            $help->update([
                'status' => 'done',
                'ai_response' => $text,
                'error_message' => null,
            ]);

            $providerUsed = $client->getLastProvider() ?: $provider;
            $modelUsed = $client->getLastModel() ?: $model;

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'provider' => $providerUsed,
                'model' => $modelUsed,
            ]);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $help->update([
                'status' => 'failed',
                'error_message' => $message,
            ]);

            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => $message,
            ]);

            throw $e;
        }
    }
}
