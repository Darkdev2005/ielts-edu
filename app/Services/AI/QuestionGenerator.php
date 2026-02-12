<?php

namespace App\Services\AI;

use App\Models\Lesson;

class QuestionGenerator
{
    private ?string $lastProvider = null;
    private ?string $lastModel = null;

    public function __construct(private AIClient $client)
    {
    }

    public function generate(Lesson $lesson, int $count = 5): array
    {
        $content = trim((string) $lesson->content_text);

        if ($content === '') {
            throw new \RuntimeException('Lesson content_text is required to generate questions.');
        }

        $system = 'You are an English exam item writer. Return ONLY valid JSON.';
        $user = [
            "Create {$count} MCQ questions for a {$lesson->difficulty} CEFR {$lesson->type} lesson.",
            'Guidelines:',
            '- 1 clearly correct answer, 3 plausible distractors.',
            '- Avoid trick/negative wording (no "NOT/EXCEPT").',
            '- Keep options similar length and style.',
            '- Base every question strictly on the lesson content.',
            '- Use clear language for the CEFR level.',
            '- Prefer comprehension and inference over trivia.',
            '- Do not quote long passages; keep prompts concise.',
            'Each question must have 4 options. correct_answer must be one of A/B/C/D.',
            'Return JSON in this exact shape:',
            '{"questions":[{"prompt":"...","options":["Option text A","Option text B","Option text C","Option text D"],"correct_answer":"A","explanation":"..."}]}',
            "Lesson content:\n".$content,
        ];

        $response = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => implode("\n", $user)],
        ], null, [
            'response_mime_type' => 'application/json',
        ]);

        $this->lastProvider = $this->client->getLastProvider();
        $this->lastModel = $this->client->getLastModel();

        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = $this->client->extractJson($content);

        return $data['questions'] ?? [];
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
