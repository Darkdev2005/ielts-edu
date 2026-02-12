<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Services\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_access_by_plan(): void
    {
        $free = Plan::create(['slug' => 'free', 'name' => 'Free']);
        $plus = Plan::create(['slug' => 'plus', 'name' => 'Plus']);
        $pro = Plan::create(['slug' => 'pro', 'name' => 'Pro']);

        $writing = Feature::create(['key' => 'writing_ai', 'name' => 'Writing AI']);
        $speaking = Feature::create(['key' => 'speaking_ai', 'name' => 'Speaking AI']);

        $free->features()->sync([]);
        $plus->features()->sync([$writing->id]);
        $pro->features()->sync([$speaking->id]);

        $user = User::factory()->create(['current_plan_id' => $free->id]);

        $gate = app(FeatureGate::class);

        $this->assertFalse($gate->userCan($user, 'writing_ai'));

        $user->current_plan_id = $plus->id;
        $user->save();
        $this->assertTrue($gate->userCan($user, 'writing_ai'));

        $user->current_plan_id = $pro->id;
        $user->save();
        $this->assertTrue($gate->userCan($user, 'speaking_ai'));
        $this->assertFalse($gate->userCan($user, 'writing_ai'));
    }
}
