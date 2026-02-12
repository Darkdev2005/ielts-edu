<?php

namespace App\Console\Commands;

use App\Models\AIGenerationLog;
use App\Models\AppSetting;
use App\Models\AttemptAnswer;
use App\Models\GrammarTopic;
use App\Models\Lesson;
use App\Jobs\GenerateAnswerExplanation;
use App\Jobs\GenerateGrammarExercises;
use App\Jobs\GenerateLessonQuestions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AIRetryFailedLogs extends Command
{
    protected $signature = 'ai:retry-failed {--limit=20} {--minutes=10}';
    protected $description = 'Auto-retry failed AI logs with rate-limit errors.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $minutes = (int) $this->option('minutes');
        $threshold = Carbon::now()->subMinutes(max(1, $minutes));
        $maxAttempts = (int) config('services.ai.retry_max_attempts', 3);

        if (Schema::hasTable('app_settings')) {
            $maxAttempts = (int) AppSetting::getValue('ai_retry_max_attempts', $maxAttempts);
        }

        $logs = AIGenerationLog::query()
            ->where('status', 'failed')
            ->where('retry_count', '<', $maxAttempts)
            ->whereNotNull('error_message')
            ->where('finished_at', '<=', $threshold)
            ->orderBy('finished_at')
            ->limit($limit)
            ->get();

        $queued = 0;

        foreach ($logs as $log) {
            if (!$log->isRateLimitError()) {
                continue;
            }

            $meta = (array) ($log->meta ?? []);
            $summary = (string) ($log->input_summary ?? '');

            $extractInt = function (string $pattern) use ($summary): ?int {
                if (preg_match($pattern, $summary, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            };

            if ($log->job_type === 'answer_explanation') {
                $answerId = (int) ($meta['answer_id'] ?? ($extractInt('/answer_id=(\d+)/') ?? 0));
                $answer = $answerId ? AttemptAnswer::find($answerId) : null;
                if (!$answer || $answer->is_correct) {
                    continue;
                }

                $answer->update(['ai_explanation' => null]);
                GenerateAnswerExplanation::dispatch($answer->id);
                $log->increment('retry_count');
                $this->appendRetryNote($log, 'Auto-retry queued');
                $queued += 1;
                continue;
            }

            if ($log->job_type === 'lesson_questions') {
                $lessonId = (int) ($meta['lesson_id'] ?? ($extractInt('/lesson_id=(\d+)/') ?? 0));
                $count = (int) ($meta['count'] ?? ($extractInt('/count=(\d+)/') ?? 5));
                $lesson = $lessonId ? Lesson::find($lessonId) : null;
                if (!$lesson) {
                    continue;
                }

                GenerateLessonQuestions::dispatch($lesson->id, $count);
                $log->increment('retry_count');
                $this->appendRetryNote($log, 'Auto-retry queued');
                $queued += 1;
                continue;
            }

            if ($log->job_type === 'grammar_exercises') {
                $topicId = (int) ($meta['topic_id'] ?? ($extractInt('/topic_id=(\d+)/') ?? 0));
                $count = (int) ($meta['count'] ?? ($extractInt('/count=(\d+)/') ?? 10));
                $topic = $topicId ? GrammarTopic::find($topicId) : null;
                if (!$topic) {
                    continue;
                }

                GenerateGrammarExercises::dispatch($topic->id, $count);
                $log->increment('retry_count');
                $this->appendRetryNote($log, 'Auto-retry queued');
                $queued += 1;
            }
        }

        $this->info("Queued {$queued} retries.");

        return self::SUCCESS;
    }

    private function appendRetryNote(AIGenerationLog $log, string $message): void
    {
        $line = now()->format('Y-m-d H:i').' - '.$message;
        $note = trim((string) ($log->note ?? ''));
        $log->update([
            'note' => $note === '' ? $line : ($note."\n".$line),
        ]);
    }
}
