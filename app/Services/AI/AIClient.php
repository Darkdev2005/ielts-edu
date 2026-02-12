<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AIClient
{
    private ?string $lastProvider = null;
    private ?string $lastModel = null;

    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $provider = config('services.ai.provider', 'gemini');
        return $this->chatWithProvider($provider, $messages, $model, $options);
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

    public function getLastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function getLastModel(): ?string
    {
        return $this->lastModel;
    }

    private function chatWithProvider(string $provider, array $messages, ?string $model = null, array $options = []): array
    {
        if ($provider === 'gemini') {
            return $this->geminiChat($messages, $model, $options);
        }

        if ($provider === 'cohere') {
            return $this->cohereChat($messages, $model, $options);
        }

        return $this->openaiChat($messages, $model);
    }

    private function openaiChat(array $messages, ?string $model = null): array
    {
        $apiKey = config('services.openai.api_key');
        $baseUrl = rtrim(config('services.openai.base_url'), '/');
        $verifySsl = (bool) config('services.ai.verify_ssl', true);
        $model = $model ?: config('services.openai.model');

        if (!$apiKey) {
            throw new \RuntimeException('OpenAI API key is missing.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withToken($apiKey)
            ->withOptions(['verify' => $verifySsl])
            ->timeout(60)
            ->post($baseUrl.'/chat/completions', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?: $response->body();
            throw new \RuntimeException('OpenAI request failed: '.$error);
        }

        $json = $response->json();
        $json['_provider'] = 'openai';
        $json['_model'] = $model;
        $this->lastProvider = 'openai';
        $this->lastModel = $model;

        return $json;
    }

    private function geminiChat(array $messages, ?string $model = null, array $options = []): array
    {
        $apiKey = config('services.gemini.api_key');
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');
        $model = $model ?: config('services.gemini.model', 'gemini-2.0-flash');
        $verifySsl = (bool) config('services.ai.verify_ssl', true);

        if (!$apiKey) {
            throw new \RuntimeException('Gemini API key is missing.');
        }

        $system = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->filter()
            ->implode("\n");

        $userText = collect($messages)
            ->where('role', '!=', 'system')
            ->pluck('content')
            ->filter()
            ->implode("\n");

        $prompt = $system !== ''
            ? "[SYSTEM]\n".$system."\n\n[USER]\n".$userText
            : $userText;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ],
        ];

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->withOptions(['verify' => $verifySsl])
            ->timeout(60)
            ->post($baseUrl.'/models/'.$model.':generateContent', $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?: $response->body();
            throw new \RuntimeException('Gemini request failed: '.$error);
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';

        $payload = [
            'choices' => [
                [
                    'message' => [
                        'content' => $text,
                    ],
                ],
            ],
        ];

        $payload['_provider'] = 'gemini';
        $payload['_model'] = $model;
        $this->lastProvider = 'gemini';
        $this->lastModel = $model;

        return $payload;
    }

    private function cohereChat(array $messages, ?string $model = null, array $options = []): array
    {
        $apiKey = config('services.cohere.api_key');
        $baseUrl = rtrim(config('services.cohere.base_url'), '/');
        $model = $model ?: config('services.cohere.model', 'command-r');
        $verifySsl = (bool) config('services.ai.verify_ssl', true);

        if (!$apiKey) {
            throw new \RuntimeException('Cohere API key is missing.');
        }

        $system = collect($messages)
            ->where('role', 'system')
            ->pluck('content')
            ->filter()
            ->implode("\n");

        $nonSystem = collect($messages)
            ->where('role', '!=', 'system')
            ->filter(fn ($message) => !empty($message['content']))
            ->values()
            ->all();

        if (empty($nonSystem)) {
            throw new \RuntimeException('Cohere chat requires at least one non-system message.');
        }

        $last = array_pop($nonSystem);
        $chatHistory = [];
        foreach ($nonSystem as $message) {
            $role = strtolower($message['role'] ?? 'user');
            $chatHistory[] = [
                'role' => $role === 'assistant' ? 'CHATBOT' : 'USER',
                'message' => $message['content'],
            ];
        }

        $payload = [
            'model' => $model,
            'message' => $last['content'],
            'temperature' => 0.2,
        ];

        if ($system !== '') {
            $payload['preamble'] = $system;
        }

        if (!empty($chatHistory)) {
            $payload['chat_history'] = $chatHistory;
        }

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::withToken($apiKey)
            ->withOptions(['verify' => $verifySsl])
            ->timeout(60)
            ->post($baseUrl.'/chat', $payload);

        if (!$response->successful()) {
            $error = $response->json('message')
                ?: $response->json('error.message')
                ?: $response->body();
            throw new \RuntimeException('Cohere request failed: '.$error);
        }

        $text = $response->json('text') ?? '';

        $payload = [
            'choices' => [
                [
                    'message' => [
                        'content' => $text,
                    ],
                ],
            ],
        ];

        $payload['_provider'] = 'cohere';
        $payload['_model'] = $model;
        $this->lastProvider = 'cohere';
        $this->lastModel = $model;

        return $payload;
    }

}
