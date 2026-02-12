<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\WritingSubmission;
use App\Services\AI\WritingFeedbackGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWritingFeedback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $submissionId)
    {
    }

    public function handle(WritingFeedbackGenerator $generator): void
    {
        $submission = WritingSubmission::with('task')->find($this->submissionId);
        if (!$submission || $submission->status === 'done') {
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $submission->user_id,
            'job_type' => 'writing_feedback',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'submission_id='.$submission->id.'; task_id='.$submission->writing_task_id,
            'meta' => [
                'submission_id' => $submission->id,
                'task_id' => $submission->writing_task_id,
            ],
            'started_at' => now(),
        ]);

        try {
            $submission->update(['status' => 'running']);

            $data = $generator->generate($submission);

            $submission->update([
                'status' => 'done',
                'band_score' => $data['overall_band'] ?? null,
                'ai_feedback' => $data['summary'] ?? null,
                'ai_feedback_json' => $data,
                'completed_at' => now(),
            ]);

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'provider' => $generator->getLastProvider() ?: $provider,
                'model' => $generator->getLastModel() ?: $model,
            ]);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $submission->update([
                'status' => 'failed',
                'ai_error' => $message,
                'completed_at' => now(),
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
