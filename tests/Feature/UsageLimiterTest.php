<?php

namespace Tests\Feature;

use App\Exceptions\LimitExceededException;
use App\Models\User;
use App\Services\UsageLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_limit_is_enforced(): void
    {
        config(['subscriptions.limits.listening_daily' => 1]);

        $user = User::factory()->create();
        $limiter = app(UsageLimiter::class);

        $limiter->assertWithinLimit($user, 'listening_daily');
        $limiter->increment($user, 'listening_daily');

        $this->expectException(LimitExceededException::class);
        $limiter->assertWithinLimit($user, 'listening_daily');
    }
}
