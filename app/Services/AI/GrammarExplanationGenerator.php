<?php

namespace App\Services\AI;

use App\Models\GrammarAttemptAnswer;
use App\Models\User;
use App\Services\FeatureGate;

class GrammarExplanationGenerator
{
    private ?string $lastProvider = null;
    private ?string $lastModel = null;

    public function __construct(private AIClient $client, private FeatureGate $featureGate)
    {
    }

    public function generate(GrammarAttemptAnswer $answer): string
    {
        $exercise = $answer->exercise;
        $user = $answer->attempt?->user;
        $isFull = $this->isFullForUser($user);

        $system = $isFull
            ? 'You are an English tutor. Provide a detailed explanation to help the learner improve.'
            : 'You are an English tutor. Explain why the answer is wrong and what the correct one is.';
        $language = $user?->language ?: 'en';

        $formatRule = $isFull
            ? 'Format strictly: first line is a short title (5-8 words). Then 2-3 bullet lines starting with "- ". Then a final line starting with "Tip:".'
            : 'Format strictly: first line is a short title (5-8 words). Then exactly 2 bullet lines starting with "- ". No Tip line.';

        $userParts = [
            'Exercise: '.($exercise->question ?? $exercise->prompt),
            'Options: '.implode(', ', $exercise->options ?? []),
            'Correct answer: '.$exercise->correct_answer,
            'User answer: '.($answer->selected_answer ?: 'blank'),
            'Respond in language: '.$language.'.',
            $isFull
                ? 'Respond in 6-8 sentences. Explain the rule, the mistake, and add one simple tip.'
                : 'Respond in 2-3 sentences. Keep it simple and CEFR-friendly.',
            $formatRule,
        ];

        $response = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => implode("\n", $userParts)],
        ]);

        $this->lastProvider = $this->client->getLastProvider();
        $this->lastModel = $this->client->getLastModel();

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    public function getLastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function getLastModel(): ?string
    {
        return $this->lastModel;
    }

    private function isFullForUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->featureGate->userCan($user, 'ai_explanation_full');
    }
}
