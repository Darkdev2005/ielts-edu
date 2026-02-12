<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\GrammarAttemptAnswer;
use App\Services\AI\GrammarExplanationGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateGrammarAnswerExplanation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $answerId)
    {
    }

    public function handle(GrammarExplanationGenerator $generator): void
    {
        $answer = GrammarAttemptAnswer::with('exercise', 'attempt.user')->find($this->answerId);
        if (!$answer || $answer->is_correct || $answer->ai_explanation) {
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $answer->attempt?->user_id,
            'job_type' => 'grammar_answer_explanation',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'grammar_answer_id='.$answer->id.'; exercise_id='.$answer->grammar_exercise_id,
            'meta' => [
                'grammar_answer_id' => $answer->id,
                'grammar_exercise_id' => $answer->grammar_exercise_id,
            ],
            'started_at' => now(),
        ]);

        try {
            $answer->ai_explanation = $generator->generate($answer);
            $answer->save();

            if (trim((string) $answer->ai_explanation) === '') {
                $log->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => 'Empty explanation generated.',
                ]);
                return;
            }

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'provider' => $generator->getLastProvider() ?: $provider,
                'model' => $generator->getLastModel() ?: $model,
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
