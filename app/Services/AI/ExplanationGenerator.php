<?php

namespace App\Services\AI;

use App\Models\AttemptAnswer;
use App\Models\User;
use App\Services\FeatureGate;

class ExplanationGenerator
{
    private ?string $lastProvider = null;
    private ?string $lastModel = null;

    public function __construct(private AIClient $client, private FeatureGate $featureGate)
    {
    }

    public function generate(AttemptAnswer $answer): string
    {
        $question = $answer->question;
        $attemptUser = $answer->attempt?->user;
        $isFull = $this->isFullForUser($attemptUser);

        $system = $isFull
            ? 'You are an English tutor. Provide a detailed explanation to help the learner improve.'
            : 'You are an English tutor. Provide a short explanation for why the answer is wrong and the correct one.';
        $formatRule = $isFull
            ? 'Format strictly: first line is a short title (5-8 words). Then 2-3 bullet lines starting with "- ". Then a final line starting with "Tip:".'
            : 'Format strictly: first line is a short title (5-8 words). Then exactly 2 bullet lines starting with "- ". No Tip line.';

        $userPrompt = [
            'Question: '.$question->prompt,
            'Options: '.implode(', ', $question->options ?? []),
            'Correct answer: '.$question->correct_answer,
            'User answer: '.($answer->selected_answer ?: 'blank'),
            $isFull
                ? 'Respond in 6-8 sentences. Explain why the correct answer is right, why the user answer is wrong, and add one practical tip.'
                : 'Respond in 2-3 sentences. Focus on the exact mistake and the correct option.',
            $formatRule,
        ];

        $response = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => implode("\n", $userPrompt)],
        ]);

        $this->lastProvider = $this->client->getLastProvider();
        $this->lastModel = $this->client->getLastModel();

        return (string) ($response['choices'][0]['message']['content'] ?? '');
    }

    /**
     * @param AttemptAnswer[] $answers
     * @return array<int, string> keyed by answer_id
     */
    public function generateBatch(array $answers): array
    {
        if (empty($answers)) {
            return [];
        }

        $firstAnswer = $answers[0] ?? null;
        $attemptUser = $firstAnswer instanceof AttemptAnswer ? $firstAnswer->attempt?->user : null;
        $isFull = $this->isFullForUser($attemptUser);

        $items = [];
        foreach ($answers as $answer) {
            $question = $answer->question;
            $items[] = [
                'answer_id' => $answer->id,
                'question' => $question?->prompt ?? '',
                'options' => $question?->options ?? [],
                'correct_answer' => $question?->correct_answer ?? '',
                'user_answer' => $answer->selected_answer ?: 'blank',
            ];
        }

        $system = $isFull
            ? 'You are an English tutor. Provide a detailed explanation to help the learner improve. Return ONLY valid JSON.'
            : 'You are an English tutor. Provide a short explanation for why the answer is wrong and the correct one. Return ONLY valid JSON.';
        $formatRule = $isFull
            ? 'Format strictly: first line is a short title (5-8 words). Then 2-3 bullet lines starting with "- ". Then a final line starting with "Tip:".'
            : 'Format strictly: first line is a short title (5-8 words). Then exactly 2 bullet lines starting with "- ". No Tip line.';
        $userPrompt = [
            $isFull
                ? 'For each item, write 6-8 sentences explaining the mistake, the correct option, and a practical tip.'
                : 'For each item, write 2-3 sentences explaining the mistake and the correct option.',
            $formatRule,
            'Return JSON in this exact shape:',
            '{"explanations":[{"answer_id":1,"explanation":"..."}]}',
            'Items:',
            json_encode($items, JSON_UNESCAPED_UNICODE),
        ];

        $response = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => implode("\n", $userPrompt)],
        ], null, [
            'response_mime_type' => 'application/json',
        ]);

        $this->lastProvider = $this->client->getLastProvider();
        $this->lastModel = $this->client->getLastModel();

        $content = $response['choices'][0]['message']['content'] ?? '';
        $payload = $this->client->extractJson($content);

        $map = [];
        foreach (($payload['explanations'] ?? []) as $item) {
            $answerId = (int) ($item['answer_id'] ?? 0);
            $text = trim((string) ($item['explanation'] ?? ''));
            if ($answerId > 0 && $text !== '') {
                $map[$answerId] = $text;
            }
        }

        return $map;
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
