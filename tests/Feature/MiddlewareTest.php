<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'requireFeature:writing_ai'])
            ->get('/_test/feature', fn () => 'ok');
    }

    public function test_require_feature_redirects_for_free(): void
    {
        $free = Plan::create(['slug' => 'free', 'name' => 'Free']);
        Feature::create(['key' => 'writing_ai', 'name' => 'Writing AI']);

        $user = User::factory()->create(['current_plan_id' => $free->id]);

        $this->actingAs($user);

        $this->get('/_test/feature')
            ->assertRedirect(route('pricing'));
    }

    public function test_require_feature_allows_for_paid(): void
    {
        $plus = Plan::create(['slug' => 'plus', 'name' => 'Plus']);
        $writing = Feature::create(['key' => 'writing_ai', 'name' => 'Writing AI']);
        $plus->features()->sync([$writing->id]);

        $user = User::factory()->create(['current_plan_id' => $plus->id]);

        $this->actingAs($user);

        $this->get('/_test/feature')
            ->assertOk();
    }

    public function test_require_feature_returns_403_for_json(): void
    {
        $free = Plan::create(['slug' => 'free', 'name' => 'Free']);
        Feature::create(['key' => 'writing_ai', 'name' => 'Writing AI']);

        $user = User::factory()->create(['current_plan_id' => $free->id]);

        $this->actingAs($user);

        $this->getJson('/_test/feature')
            ->assertForbidden();
    }
}
