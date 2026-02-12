<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAIClient
{
    public function chat(array $messages, ?string $model = null): array
    {
        $apiKey = config('services.openai.api_key');
        $baseUrl = rtrim(config('services.openai.base_url'), '/');

        if (!$apiKey) {
            throw new \RuntimeException('OpenAI API key is missing.');
        }

        $payload = [
            'model' => $model ?: config('services.openai.model'),
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post($baseUrl.'/chat/completions', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?: $response->body();
            throw new \RuntimeException('OpenAI request failed: '.$error);
        }

        return $response->json();
    }

    public function extractJson(string $content): array
    {
        $json = trim($content);
        $json = Str::after($json, '```json');
        $json = Str::before($json, '```');

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Failed to parse AI JSON response.');
        }

        return $decoded;
    }
}
