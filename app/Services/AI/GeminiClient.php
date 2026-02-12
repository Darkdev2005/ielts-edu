<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeminiClient
{
    public function generate(string $prompt, array $context = [], array $parameters = []): array
    {
        $model = config('ai.gemini.model', 'gemini-2.5-flash');
        $baseUrl = rtrim(config('ai.gemini.base_url'), '/');
        $timeout = (int) config('ai.request_timeout_seconds', 30);
        $verifySsl = filter_var(config('ai.gemini.verify_ssl', true), FILTER_VALIDATE_BOOLEAN);

        $apiKeys = $this->resolveApiKeys();
        if (empty($apiKeys)) {
            throw new GeminiException('Gemini API key is missing.', 401);
        }

        $temperature = (float) ($parameters['temperature'] ?? config('ai.gemini.temperature', 0.4));
        $maxOutputTokens = (int) ($parameters['max_output_tokens'] ?? config('ai.gemini.max_output_tokens', 1024));

        $fullPrompt = $this->buildPrompt($prompt, $context, $parameters);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $fullPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxOutputTokens,
            ],
        ];

        $response = null;
        $lastError = null;

        for ($attempt = 0; $attempt < count($apiKeys); $attempt++) {
            $apiKey = $this->selectApiKey($apiKeys);
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->withOptions(['verify' => $verifySsl])
                ->timeout($timeout)
                ->post($baseUrl.'/models/'.$model.':generateContent', $payload);

            if ($response->successful()) {
                break;
            }

            $lastError = $response;
            $status = $response->status();
            if (!in_array($status, [401, 403, 429], true) && $status < 500) {
                break;
            }
        }

        if (!$response || !$response->successful()) {
            $error = $lastError?->json('error.message') ?: $lastError?->body() ?: 'Unknown error';
            $status = $lastError?->status() ?? 500;
            throw new GeminiException('Gemini request failed: '.$error, $status, $lastError?->json() ?? []);
        }

        $json = $response->json();
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'text' => $text,
            'raw' => $json,
            'usage' => [
                'prompt_tokens' => $json['usageMetadata']['promptTokenCount'] ?? null,
                'output_tokens' => $json['usageMetadata']['candidatesTokenCount'] ?? null,
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

    private function resolveApiKeys(): array
    {
        $pool = config('ai.gemini.api_keys', []);
        $primary = config('ai.gemini.api_key');
        if ($primary) {
            array_unshift($pool, $primary);
        }

        $deduped = [];
        foreach ($pool as $key) {
            $key = trim((string) $key);
            if ($key !== '' && !in_array($key, $deduped, true)) {
                $deduped[] = $key;
            }
        }

        return $deduped;
    }

    private function selectApiKey(array $keys): string
    {
        if (count($keys) === 1) {
            return $keys[0];
        }

        $index = Cache::increment('ai:gemini:key_index');
        if ($index === 1) {
            Cache::put('ai:gemini:key_index', 1, now()->addDays(7));
        }

        return $keys[$index % count($keys)];
    }
}
