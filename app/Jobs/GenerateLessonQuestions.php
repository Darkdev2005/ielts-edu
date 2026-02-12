<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\AI\QuestionGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateLessonQuestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $lessonId, public int $count = 5)
    {
    }

    public function handle(QuestionGenerator $generator): void
    {
        $lesson = Lesson::find($this->lessonId);
        if (!$lesson) {
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $lesson->created_by,
            'job_type' => 'lesson_questions',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'lesson_id='.$lesson->id.'; count='.$this->count,
            'meta' => [
                'lesson_id' => $lesson->id,
                'count' => $this->count,
            ],
            'started_at' => now(),
        ]);

        try {
            $questions = $generator->generate($lesson, $this->count);

            if (empty($questions)) {
                $log->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => 'No questions generated.',
                ]);
                return;
            }

            $lesson->questions()->delete();

            $created = 0;
            foreach ($questions as $q) {
                $options = $q['options'] ?? [];
                if (!is_array($options)) {
                    continue;
                }

                if (count($options) !== 4) {
                    continue;
                }

                $correct = $q['correct_answer'] ?? '';
                if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                    continue;
                }

                Question::create([
                    'lesson_id' => $lesson->id,
                    'type' => 'mcq',
                    'prompt' => $q['prompt'] ?? '',
                    'options' => $options,
                    'correct_answer' => $correct,
                    'ai_explanation' => $q['explanation'] ?? null,
                ]);
                $created += 1;
            }

            $log->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'meta' => array_merge((array) ($log->meta ?? []), ['created' => $created]),
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
