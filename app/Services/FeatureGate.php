<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FeatureGate
{
    public function userCan(User $user, string $featureKey): bool
    {
        if ($user->is_admin || $user->is_super_admin) {
            return true;
        }

        $plan = $this->currentPlan($user);
        if (!$plan) {
            return false;
        }

        $features = $this->planFeatureKeys($plan);

        return in_array($featureKey, $features, true);
    }

    public function currentPlan(User $user): ?Plan
    {
        if ($user->relationLoaded('currentPlan') && $user->currentPlan) {
            return $user->currentPlan;
        }

        if ($user->current_plan_id) {
            return Plan::with('features')->find($user->current_plan_id);
        }

        return Plan::with('features')->where('slug', 'free')->first();
    }

    protected function planFeatureKeys(Plan $plan): array
    {
        $cacheKey = "plan_features:{$plan->id}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($plan) {
            return $plan->features()->pluck('key')->all();
        });
    }
}
