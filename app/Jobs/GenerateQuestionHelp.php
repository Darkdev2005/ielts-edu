<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\QuestionAIHelp;
use App\Services\AI\AIClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateQuestionHelp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $helpId)
    {
    }

    public function handle(AIClient $client): void
    {
        $help = QuestionAIHelp::with(['question', 'user'])->find($this->helpId);
        if (!$help || $help->status !== 'queued') {
            return;
        }

        $question = $help->question;
        if (!$question) {
            $help->update([
                'status' => 'failed',
                'error_message' => 'Question not found.',
            ]);
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $help->user_id,
            'job_type' => 'question_help',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'question_id='.$question->id.'; help_id='.$help->id,
            'meta' => [
                'question_id' => $question->id,
                'help_id' => $help->id,
            ],
            'started_at' => now(),
        ]);

        try {
            $system = 'You are an English tutor. Explain the question and correct answer clearly and briefly.';
            $userParts = [
                'Question: '.$question->prompt,
                'Options: '.implode(', ', $question->options ?? []),
                'Correct answer: '.$question->correct_answer,
            ];

            if ($help->user_prompt) {
                $userParts[] = 'User request: '.$help->user_prompt;
                $userParts[] = 'Respond in the same language as the user request.';
            } else {
                $language = $help->user?->language ?: 'en';
                $userParts[] = 'Respond in language: '.$language.'.';
            }

            $userParts[] = 'Respond in 2-4 sentences. Keep it simple and CEFR-friendly.';

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
