<?php

namespace App\Services\AI;

use App\Models\WritingSubmission;

class WritingFeedbackGenerator
{
    private ?string $lastProvider = null;
    private ?string $lastModel = null;

    public function __construct(private AIClient $client)
    {
    }

    public function generate(WritingSubmission $submission): array
    {
        $task = $submission->task;
        $taskType = strtoupper((string) $task?->task_type);

        $system = <<<SYS
You are an IELTS writing examiner. Respond strictly in JSON.
Provide band score and feedback by criteria.
Use this JSON schema:
{
  "overall_band": 0.0,
  "criteria": {
    "task_response": {"band": 0.0, "notes": "string"},
    "coherence_cohesion": {"band": 0.0, "notes": "string"},
    "lexical_resource": {"band": 0.0, "notes": "string"},
    "grammar_range_accuracy": {"band": 0.0, "notes": "string"}
  },
  "strengths": ["string"],
  "weaknesses": ["string"],
  "improvements": ["string"],
  "summary": "string"
}
SYS;

        $prompt = "Task type: {$taskType}\n"
            ."Task prompt: {$task?->prompt}\n\n"
            ."Candidate response:\n{$submission->response_text}\n\n"
            ."Give detailed, constructive feedback. Use IELTS band scale 0-9.";

        $response = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ]);

        $this->lastProvider = $this->client->getLastProvider();
        $this->lastModel = $this->client->getLastModel();

        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = $this->client->extractJson($content);

        return $data;
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
