<?php

namespace App\Jobs;

use App\Models\AIGenerationLog;
use App\Models\GrammarExercise;
use App\Models\GrammarTopic;
use App\Services\AI\GrammarExerciseGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateGrammarExercises implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $topicId, private readonly int $count = 10)
    {
    }

    public function handle(GrammarExerciseGenerator $generator): void
    {
        $topic = GrammarTopic::with('rules')->find($this->topicId);
        if (!$topic) {
            return;
        }

        $provider = config('services.ai.provider', 'gemini');
        $model = config('services.'.$provider.'.model');
        $startedAt = microtime(true);

        $log = AIGenerationLog::create([
            'user_id' => $topic->created_by,
            'job_type' => 'grammar_exercises',
            'provider' => $provider,
            'model' => $model,
            'status' => 'running',
            'input_summary' => 'topic_id='.$topic->id.'; count='.$this->count,
            'meta' => [
                'topic_id' => $topic->id,
                'count' => $this->count,
            ],
            'started_at' => now(),
        ]);

        try {
            $items = $generator->generate($topic, $this->count);
            if (empty($items)) {
                $log->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'error_message' => 'No exercises generated.',
                ]);
                return;
            }

            $maxSort = (int) ($topic->exercises()->max('sort_order') ?? 0);
            $offset = 1;
            $created = 0;
            $ruleMap = $topic->rules->keyBy('rule_key');
            $unmappedRuleId = $ruleMap->get('__unmapped__')?->id;
            if (!$unmappedRuleId) {
                $unmapped = $topic->rules()->create([
                    'rule_key' => '__unmapped__',
                    'rule_type' => 'note',
                    'rule_text_uz' => "Ushbu mashq hali aniq qoidaga bog'lanmagan.",
                    'title' => 'Unmapped rule',
                    'content' => 'System placeholder for exercises without a mapped rule.',
                    'sort_order' => 9999,
                ]);
                $unmappedRuleId = $unmapped->id;
                $ruleMap = $topic->rules()->get()->keyBy('rule_key');
            }

            foreach ($items as $item) {
                $ruleKey = trim((string) ($item['rule_key'] ?? ''));
                $ruleId = $ruleMap->get($ruleKey)?->id ?? $unmappedRuleId;
                $type = strtolower(trim((string) ($item['exercise_type'] ?? $item['type'] ?? 'mcq')));
                $question = trim((string) ($item['question'] ?? $item['prompt'] ?? ''));
                $optionsRaw = $item['options'] ?? [];
                $correct = trim((string) ($item['correct_answer'] ?? ''));
                $explanation = trim((string) ($item['explanation_en'] ?? $item['explanation'] ?? '')) ?: null;

                if ($question === '') {
                    continue;
                }

                $options = [];
                if (is_array($optionsRaw)) {
                    $keys = array_keys($optionsRaw);
                    $isAssoc = array_keys($keys) !== $keys;
                    if ($isAssoc) {
                        foreach ($optionsRaw as $key => $value) {
                            $options[strtoupper((string) $key)] = trim((string) $value);
                        }
                    } else {
                        $options = [
                            'A' => trim((string) ($optionsRaw[0] ?? '')),
                            'B' => trim((string) ($optionsRaw[1] ?? '')),
                            'C' => trim((string) ($optionsRaw[2] ?? '')),
                            'D' => trim((string) ($optionsRaw[3] ?? '')),
                        ];
                    }
                }

                if ($type === 'mcq') {
                    if (in_array('', $options, true)) {
                        continue;
                    }
                    $correct = strtoupper(substr($correct, 0, 1));
                    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                        continue;
                    }
                } elseif ($type === 'tf') {
                    $correct = strtolower($correct) === 'true' ? 'true' : 'false';
                }

                GrammarExercise::create([
                    'grammar_topic_id' => $topic->id,
                    'grammar_rule_id' => $ruleId,
                    'exercise_type' => $type,
                    'type' => $type,
                    'question' => $question,
                    'prompt' => $question,
                    'options' => $options,
                    'correct_answer' => $correct,
                    'explanation' => $explanation,
                    'explanation_en' => $explanation,
                    'sort_order' => $maxSort + $offset,
                ]);

                $offset += 1;
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
