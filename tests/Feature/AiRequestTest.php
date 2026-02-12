<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiRequestJob;
use App\Models\AiCache;
use App\Models\AiRequest;
use App\Models\User;
use App\Services\AI\GeminiClient;
use App\Services\AI\LockManagerMySql;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.provider', 'gemini');
        config()->set('ai.gemini.model', 'gemini-2.5-flash');
    }

    public function test_rate_limiting_blocks_after_limit(): void
    {
        config()->set('ai.user_rpm', 1);
        config()->set('ai.global_rpm', 1);

        $user = User::factory()->create();

        $payload = [
            'task' => 'generic',
            'prompt' => 'Hello',
            'context' => [],
        ];

        $this->actingAs($user)->postJson('/api/ai/requests', $payload)->assertStatus(202);
        $this->actingAs($user)->postJson('/api/ai/requests', $payload)->assertStatus(429);
    }

    public function test_cache_hit_returns_immediately(): void
    {
        Queue::fake();

        $payload = [
            'task' => 'generic',
            'prompt' => 'Hello',
            'context' => ['a' => 1],
            'parameters' => ['temperature' => 0.4],
        ];

        $provider = config('ai.provider', 'gemini');
        $model = $provider === 'groq'
            ? config('ai.groq.model', 'llama-3.1-8b-instant')
            : config('ai.gemini.model', 'gemini-2.5-flash');

        $hash = sha1(json_encode([
            'provider' => $provider,
            'model' => $model,
            'task' => $payload['task'],
            'prompt' => $payload['prompt'],
            'context' => $this->normalize(['a' => 1]),
            'parameters' => $this->normalize(['temperature' => 0.4]),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        AiCache::create([
            'dedup_hash' => $hash,
            'response_json' => ['text' => 'cached'],
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/ai/requests', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'done');
        Queue::assertNothingPushed();
    }

    public function test_request_queued_and_marked_done(): void
    {
        Queue::fake();

        $payload = [
            'task' => 'generic',
            'prompt' => 'Explain this',
            'context' => [],
        ];

        $response = $this->postJson('/api/ai/requests', $payload)->assertStatus(202);
        $requestId = $response->json('data.request_id');

        $fake = new class extends GeminiClient {
            public function generate(string $prompt, array $context = [], array $parameters = []): array
            {
                return [
                    'text' => 'ok',
                    'raw' => ['id' => 'test'],
                    'usage' => ['prompt_tokens' => 5, 'output_tokens' => 5],
                    'model' => 'gemini-2.5-flash',
                ];
            }
        };

        app()->instance(GeminiClient::class, $fake);

        $job = new ProcessAiRequestJob($requestId);
        $job->handle(app(GeminiClient::class), app(\App\Services\AI\GroqClient::class), app(LockManagerMySql::class));

        $this->assertDatabaseHas('ai_requests', [
            'id' => $requestId,
            'status' => 'done',
        ]);
    }

    private function normalize(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value);
            }
        }
        return $data;
    }
}
