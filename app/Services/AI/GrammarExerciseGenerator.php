<?php

namespace App\Services\AI;

use App\Models\GrammarTopic;

class GrammarExerciseGenerator
{
    private ?string $lastProvider = null;
    private ?string $lastModel = null;

    public function __construct(private readonly AIClient $client)
    {
    }

    public function generate(GrammarTopic $topic, int $count = 10): array
    {
        $rules = $topic->rules()
            ->visible()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['rule_key', 'rule_text_uz', 'rule_text_en', 'formula', 'example_uz', 'example_en'])
            ->map(function ($rule) {
                $text = $rule->rule_text_uz ?: $rule->rule_text_en ?: '';
                $formula = $rule->formula ? " Formula: {$rule->formula}" : '';
                $example = $rule->example_uz ?: $rule->example_en ?: '';
                $example = $example !== '' ? " Example: {$example}" : '';
                return "{$rule->rule_key}: {$text}{$formula}{$example}";
            })
            ->implode("\n");

        $system = 'You are an English grammar teacher. Create clear multiple-choice exercises.';

        $user = "Topic: {$topic->title}\n"
            ."Description: ".($topic->description ?: 'N/A')."\n"
            ."Rules (use rule_key):\n".($rules ?: 'N/A')."\n\n"
            ."Create {$count} exercises. Return JSON only in this format:\n"
            ."{\"exercises\":[{\"rule_key\":\"...\",\"exercise_type\":\"mcq\",\"question\":\"...\",\"options\":{\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"},\"correct_answer\":\"B\",\"explanation_en\":\"...\"}]}";

        $response = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ]);

        $this->lastProvider = $this->client->getLastProvider();
        $this->lastModel = $this->client->getLastModel();

        $content = $response['choices'][0]['message']['content'] ?? '';
        $payload = $this->client->extractJson($content);

        $exercises = $payload['exercises'] ?? [];
        if (!is_array($exercises)) {
            return [];
        }

        return $exercises;
    }

    public function getLastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function getLastModel(): ?string
    {
        return $this->lastModel;
    }
}
