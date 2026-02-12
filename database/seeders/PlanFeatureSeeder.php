<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlanFeatureSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'free' => [
                'name' => 'Free',
                'price_monthly' => 0,
                'is_active' => true,
                'is_visible' => true,
                'sort_order' => 1,
            ],
            'plus' => [
                'name' => 'Plus',
                'price_monthly' => 9900,
                'is_active' => true,
                'is_visible' => true,
                'sort_order' => 2,
            ],
            'pro' => [
                'name' => 'Pro',
                'price_monthly' => 14900,
                'is_active' => false,
                'is_visible' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $slug => $data) {
            Plan::updateOrCreate(['slug' => $slug], $data);
        }

        $features = [
            'reading_basic' => ['name' => 'Reading Basic', 'description' => 'A1-A2 with daily limits'],
            'listening_basic' => ['name' => 'Listening Basic', 'description' => 'Limited daily attempts'],
            'ai_explanation' => ['name' => 'AI Explanation (Short)', 'description' => 'Short AI hints'],
            'analytics_basic' => ['name' => 'Analytics Basic', 'description' => 'Last 7 days only'],
            'writing_ai' => ['name' => 'Writing AI', 'description' => 'Task 1 & 2 feedback + model answer'],
            'reading_full' => ['name' => 'Reading Full', 'description' => 'All levels'],
            'listening_full' => ['name' => 'Listening Full', 'description' => 'All lessons'],
            'reading_pro' => ['name' => 'Reading Pro', 'description' => 'B2+ lessons'],
            'listening_pro' => ['name' => 'Listening Pro', 'description' => 'B2+ lessons'],
            'ai_explanation_full' => ['name' => 'AI Explanation Full', 'description' => 'Detailed explanations'],
            'analytics_full' => ['name' => 'Analytics Full', 'description' => 'Charts + recommendations'],
            'speaking_ai' => ['name' => 'Speaking AI', 'description' => 'Part 1-3 feedback'],
            'mock_tests' => ['name' => 'Mock Tests (Reading + Listening)', 'description' => 'IELTS-style exam simulation'],
        ];

        foreach ($features as $key => $data) {
            Feature::updateOrCreate(['key' => $key], $data);
        }

        $planFeatureMap = [
            'free' => ['reading_basic', 'listening_basic', 'ai_explanation', 'analytics_basic'],
            'plus' => ['writing_ai', 'reading_full', 'listening_full', 'mock_tests', 'ai_explanation_full', 'analytics_full'],
            'pro' => ['speaking_ai', 'reading_full', 'listening_full', 'mock_tests', 'reading_pro', 'listening_pro', 'ai_explanation_full'],
        ];

        foreach ($planFeatureMap as $planSlug => $featureKeys) {
            $plan = Plan::where('slug', $planSlug)->first();
            if (!$plan) {
                continue;
            }
            $featureIds = Feature::whereIn('key', $featureKeys)->pluck('id');
            $plan->features()->sync($featureIds);
        }

        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            User::whereNull('current_plan_id')->update(['current_plan_id' => $freePlan->id]);
        }
    }
}
