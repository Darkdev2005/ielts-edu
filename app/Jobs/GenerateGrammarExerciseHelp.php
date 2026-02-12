<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\GrammarExerciseAIHelp;
use App\Services\AI\AIClient;
use App\Services\FeatureGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateGrammarExerciseHelp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $helpId)
    {
    }

    public function handle(AIClient $client, FeatureGate $featureGate): void
    {
        $help = GrammarExerciseAIHelp::with(['exercise', 'user'])->find($this->helpId);
        if (!$help || $help->status !== 'queued') {
            return;
        }

        $exercise = $help->exercise;
        if (!$exercise) {
            $help->update([
                'status' => 'failed',
                'error_message' => 'Exercise not found.',
            ]);
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $help->user_id,
            'job_type' => 'grammar_exercise_help',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'grammar_exercise_id='.$exercise->id.'; help_id='.$help->id,
            'meta' => [
                'grammar_exercise_id' => $exercise->id,
                'help_id' => $help->id,
            ],
            'started_at' => now(),
        ]);

        try {
            $isFull = $help->user ? $featureGate->userCan($help->user, 'ai_explanation_full') : false;
            $system = $isFull
                ? 'You are an English tutor. Provide a detailed explanation to help the learner understand the rule.'
                : 'You are an English tutor. Explain this grammar question and the correct answer briefly.';
            $formatRule = $isFull
                ? 'Format strictly: first line is a short title (5-8 words). Then 2-3 bullet lines starting with "- ". Then a final line starting with "Tip:".'
                : 'Format strictly: first line is a short title (5-8 words). Then exactly 2 bullet lines starting with "- ". No Tip line.';
            $userParts = [
                'Exercise: '.$exercise->prompt,
                'Options: '.implode(', ', $exercise->options ?? []),
                'Correct answer: '.$exercise->correct_answer,
            ];

            if ($help->user_prompt) {
                $userParts[] = 'User request: '.$help->user_prompt;
                $userParts[] = 'Respond in the same language as the user request.';
            } else {
                $language = $help->user?->language ?: 'en';
                $userParts[] = 'Respond in language: '.$language.'.';
            }

            $userParts[] = $isFull
                ? 'Respond in 6-8 sentences. Explain the rule, why the answer is correct, and add one short example.'
                : 'Respond in 2-4 sentences. Keep it simple and CEFR-friendly.';
            $userParts[] = $formatRule;
            $userParts[] = 'Use plain text only. Do not use markdown headings or numbering.';

            $response = $client->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => implode("\n", $userParts)],
            ]);

            $text = (string) ($response['choices'][0]['message']['content'] ?? '');
            if ($text === '') {
                throw new \RuntimeException('Empty AI response.');
            }

            $help->update([
                'ai_response' => $text,
                'status' => 'done',
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
