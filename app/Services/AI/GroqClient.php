<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;

class GroqClient
{
    public function generate(string $prompt, array $context = [], array $parameters = []): array
    {
        $apiKey = config('ai.groq.api_key');
        $model = config('ai.groq.model', 'llama-3.1-8b-instant');
        $baseUrl = rtrim(config('ai.groq.base_url'), '/');
        $timeout = (int) config('ai.request_timeout_seconds', 30);
        $verifySsl = filter_var(config('ai.groq.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);

        if (!$apiKey) {
            throw new GroqException('Groq API key is missing.', 401);
        }

        $temperature = (float) ($parameters['temperature'] ?? config('ai.groq.temperature', 0.4));
        $maxOutputTokens = (int) ($parameters['max_output_tokens'] ?? config('ai.groq.max_output_tokens', 1024));

        $fullPrompt = $this->buildPrompt($prompt, $context, $parameters);

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $fullPrompt,
                ],
            ],
            'temperature' => $temperature,
            'max_tokens' => $maxOutputTokens,
        ];

        $response = Http::withToken($apiKey)
            ->withOptions(['verify' => $verifySsl])
            ->timeout($timeout)
            ->post($baseUrl.'/chat/completions', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?: $response->body() ?: 'Unknown error';
            $status = $response->status();
            throw new GroqException('Groq request failed: '.$error, $status, $response->json() ?? []);
        }

        $json = $response->json();
        $text = $json['choices'][0]['message']['content'] ?? '';

        return [
            'text' => $text,
            'raw' => $json,
            'usage' => [
                'prompt_tokens' => $json['usage']['prompt_tokens'] ?? null,
                'output_tokens' => $json['usage']['completion_tokens'] ?? null,
            ],
            'model' => $model,
        ];
    }

    private function buildPrompt(string $prompt, array $context, array $parameters): string
    {
        $task = (string) ($parameters['task'] ?? 'generic');
        $contextText = '';
        if (!empty($context)) {
            $contextText = "Context:\n".json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $parts = array_filter([
            "Task: {$task}",
            "Prompt:\n".$prompt,
            $contextText,
        ]);

        return implode("\n\n", $parts);
    }
}
