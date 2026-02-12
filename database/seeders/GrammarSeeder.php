<?php

namespace Database\Seeders;

use App\Models\GrammarExercise;
use App\Models\GrammarRule;
use App\Models\GrammarTopic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GrammarSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstWhere('is_admin', true);
        if (!$admin) {
            return;
        }

        $topicSlug = Str::slug('Present Simple');
        $topic = GrammarTopic::updateOrCreate(
            ['slug' => $topicSlug],
            [
                'title' => 'Present Simple',
                'description' => 'Talk about routines, facts, and general truths.',
                'cefr_level' => 'A2',
                'created_by' => $admin->id,
            ]
        );

        $rules = [
            [
                'title' => 'Form',
                'content' => "Use the base verb for I/you/we/they.\nAdd -s or -es for he/she/it.",
                'sort_order' => 1,
            ],
            [
                'title' => 'Negatives and questions',
                'content' => "Use do/does + not for negatives.\nUse Do/Does at the beginning for questions.",
                'sort_order' => 2,
            ],
        ];

        foreach ($rules as $rule) {
            GrammarRule::updateOrCreate(
                [
                    'grammar_topic_id' => $topic->id,
                    'title' => $rule['title'],
                ],
                $rule + ['grammar_topic_id' => $topic->id]
            );
        }

        $exercises = [
            [
                'prompt' => 'She ___ to work by bus every day.',
                'options' => ['go', 'goes', 'going', 'gone'],
                'correct_answer' => 'B',
                'explanation' => 'He/She/It takes -s in the present simple.',
                'sort_order' => 1,
            ],
            [
                'prompt' => 'They ___ coffee in the morning.',
                'options' => ['drink', 'drinks', 'drank', 'drinking'],
                'correct_answer' => 'A',
                'explanation' => 'They uses the base verb without -s.',
                'sort_order' => 2,
            ],
            [
                'prompt' => '___ he play football on Sundays?',
                'options' => ['Do', 'Does', 'Did', 'Is'],
                'correct_answer' => 'B',
                'explanation' => 'Questions with he/she/it use "Does".',
                'sort_order' => 3,
            ],
            [
                'prompt' => 'I ___ not like spicy food.',
                'options' => ['do', 'does', 'am', 'is'],
                'correct_answer' => 'A',
                'explanation' => 'Use "do not" with I/you/we/they.',
                'sort_order' => 4,
            ],
            [
                'prompt' => 'The sun ___ in the east.',
                'options' => ['rise', 'rises', 'rising', 'rose'],
                'correct_answer' => 'B',
                'explanation' => 'Facts use present simple; "sun" is he/she/it.',
                'sort_order' => 5,
            ],
        ];

        foreach ($exercises as $exercise) {
            $payload = [
                'full_sentence' => $exercise['prompt'],
                'options' => [
                    'a' => $exercise['options'][0] ?? null,
                    'b' => $exercise['options'][1] ?? null,
                    'c' => $exercise['options'][2] ?? null,
                    'd' => $exercise['options'][3] ?? null,
                ],
                'explanation_en' => $exercise['explanation'] ?? null,
            ];
            $payload = collect($payload)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() ? $payload : null;

            GrammarExercise::updateOrCreate(
                [
                    'grammar_topic_id' => $topic->id,
                    'prompt' => $exercise['prompt'],
                ],
                $exercise + [
                    'grammar_topic_id' => $topic->id,
                    'type' => 'mcq',
                    'payload_json' => $payload,
                ]
            );
        }
    }
}
