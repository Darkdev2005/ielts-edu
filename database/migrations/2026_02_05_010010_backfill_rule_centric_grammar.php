<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedTopicKeys = [];
        $topics = DB::table('grammar_topics')
            ->select('id', 'slug', 'title', 'description', 'topic_key', 'title_uz', 'description_uz')
            ->orderBy('id')
            ->get();

        foreach ($topics as $topic) {
            $topicKey = trim((string) $topic->topic_key);
            if ($topicKey === '') {
                $base = trim((string) $topic->slug);
                if ($base === '') {
                    $base = Str::slug((string) $topic->title);
                }
                if ($base === '') {
                    $base = 'topic-'.$topic->id;
                }
                $topicKey = $base;
                $suffix = 2;
                while (in_array($topicKey, $usedTopicKeys, true)) {
                    $topicKey = $base.'-'.$suffix;
                    $suffix += 1;
                }
            }

            $usedTopicKeys[] = $topicKey;

            $updates = [];
            if ((string) $topic->topic_key !== $topicKey) {
                $updates['topic_key'] = $topicKey;
            }

            if ((string) $topic->title_uz === '' && (string) $topic->title !== '') {
                $updates['title_uz'] = (string) $topic->title;
            }

            if ((string) $topic->description_uz === '' && (string) $topic->description !== '') {
                $updates['description_uz'] = (string) $topic->description;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('grammar_topics')->where('id', $topic->id)->update($updates);
            }
        }

        $unmappedByTopic = [];
        $topics = DB::table('grammar_topics')->select('id', 'title_uz', 'title')->get();
        foreach ($topics as $topic) {
            $existing = DB::table('grammar_rules')
                ->where('grammar_topic_id', $topic->id)
                ->where('rule_key', '__unmapped__')
                ->first();

            if ($existing) {
                $unmappedByTopic[$topic->id] = $existing->id;
                continue;
            }

            $label = (string) ($topic->title_uz ?: $topic->title ?: 'Topic');
            $content = "System placeholder for exercises without a mapped rule. Topic: {$label}.";

            $id = DB::table('grammar_rules')->insertGetId([
                'grammar_topic_id' => $topic->id,
                'rule_key' => '__unmapped__',
                'rule_type' => 'note',
                'title' => 'Unmapped rule',
                'content' => $content,
                'rule_text_uz' => "Ushbu mashq hali aniq qoidaga bog'lanmagan.",
                'sort_order' => 9999,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $unmappedByTopic[$topic->id] = $id;
        }

        DB::table('grammar_rules')
            ->select('id', 'grammar_topic_id', 'rule_id', 'rule_key', 'title', 'content', 'rule_text_uz', 'level', 'cefr_level')
            ->orderBy('id')
            ->chunkById(200, function ($rules) {
                foreach ($rules as $rule) {
                    $updates = [];

                    $ruleKey = trim((string) $rule->rule_key);
                    if ($ruleKey === '') {
                        $ruleKey = trim((string) $rule->rule_id);
                    }
                    if ($ruleKey === '') {
                        $ruleKey = Str::slug((string) $rule->title);
                    }
                    if ($ruleKey === '') {
                        $ruleKey = 'rule-'.$rule->id;
                    }

                    $existing = DB::table('grammar_rules')
                        ->where('grammar_topic_id', $rule->grammar_topic_id)
                        ->where('rule_key', $ruleKey)
                        ->where('id', '!=', $rule->id)
                        ->exists();

                    if ($existing) {
                        $ruleKey = $ruleKey.'-'.$rule->id;
                    }

                    if ((string) $rule->rule_key !== $ruleKey) {
                        $updates['rule_key'] = $ruleKey;
                    }

                    if ((string) $rule->rule_text_uz === '' && (string) $rule->content !== '') {
                        $updates['rule_text_uz'] = (string) $rule->content;
                    }

                    if ((string) $rule->cefr_level === '' && (string) $rule->level !== '') {
                        $updates['cefr_level'] = (string) $rule->level;
                    }

                    if (!empty($updates)) {
                        $updates['updated_at'] = now();
                        DB::table('grammar_rules')->where('id', $rule->id)->update($updates);
                    }
                }
            });

        $unmappedLookup = collect($unmappedByTopic);

        DB::table('grammar_exercises')
            ->select('id', 'grammar_topic_id', 'grammar_rule_id', 'type', 'exercise_type', 'prompt', 'question', 'options', 'explanation', 'explanation_uz')
            ->orderBy('id')
            ->chunkById(200, function ($exercises) use ($unmappedLookup) {
                foreach ($exercises as $exercise) {
                    $updates = [];

                    if ((string) $exercise->question === '' && (string) $exercise->prompt !== '') {
                        $updates['question'] = (string) $exercise->prompt;
                    }

                    if ((string) $exercise->exercise_type === '') {
                        $type = (string) $exercise->type;
                        $updates['exercise_type'] = $type !== '' ? $type : 'mcq';
                    }

                    if ((string) $exercise->explanation_uz === '' && (string) $exercise->explanation !== '') {
                        $updates['explanation_uz'] = (string) $exercise->explanation;
                    }

                    if ($exercise->grammar_rule_id === null) {
                        $ruleId = $unmappedLookup->get($exercise->grammar_topic_id);
                        if ($ruleId) {
                            $updates['grammar_rule_id'] = $ruleId;
                        }
                    }

                    $options = $exercise->options;
                    $decoded = null;
                    if (is_string($options)) {
                        $decoded = json_decode($options, true);
                    } elseif (is_array($options)) {
                        $decoded = $options;
                    }

                    if (is_array($decoded)) {
                        $keys = array_keys($decoded);
                        $isAssoc = array_keys($keys) !== $keys;

                        if (!$isAssoc) {
                            $mapped = [
                                'A' => $decoded[0] ?? '',
                                'B' => $decoded[1] ?? '',
                                'C' => $decoded[2] ?? '',
                                'D' => $decoded[3] ?? '',
                            ];
                            $updates['options'] = json_encode($mapped, JSON_UNESCAPED_UNICODE);
                        } else {
                            $mapped = [];
                            foreach ($decoded as $key => $value) {
                                $mapped[strtoupper((string) $key)] = $value;
                            }
                            $updates['options'] = json_encode($mapped, JSON_UNESCAPED_UNICODE);
                        }
                    }

                    if (!empty($updates)) {
                        $updates['updated_at'] = now();
                        DB::table('grammar_exercises')->where('id', $exercise->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('grammar_rules')->where('rule_key', '__unmapped__')->delete();
    }
};
