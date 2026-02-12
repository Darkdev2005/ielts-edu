<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\AttemptAnswer;
use App\Services\AI\ExplanationGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAnswerExplanation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $answerId)
    {
    }

    public function handle(ExplanationGenerator $generator): void
    {
        $answer = AttemptAnswer::with('question', 'attempt')->find($this->answerId);
        if (!$answer || $answer->is_correct || $answer->ai_explanation) {
            return;
        }

        $question = $answer->question;

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $answer->attempt?->user_id,
            'job_type' => 'answer_explanation',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'answer_id='.$answer->id.'; question_id='.$answer->question_id,
            'meta' => [
                'answer_id' => $answer->id,
                'question_id' => $answer->question_id,
            ],
            'started_at' => now(),
        ]);

        try {
            $answer->ai_explanation = $generator->generate($answer);
            $answer->save();

            $providerUsed = $generator->getLastProvider() ?: $provider;
            $modelUsed = $generator->getLastModel() ?: $model;

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'provider' => $providerUsed,
                'model' => $modelUsed,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_message' => (string) $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
