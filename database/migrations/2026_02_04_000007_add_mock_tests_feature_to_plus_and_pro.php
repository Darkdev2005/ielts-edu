<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('features')->updateOrInsert(
            ['key' => 'mock_tests'],
            [
                'name' => 'Mock Tests (Reading + Listening)',
                'description' => 'Full mock exam simulation for reading and listening.',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $featureId = DB::table('features')->where('key', 'mock_tests')->value('id');
        if (!$featureId) {
            return;
        }

        $planIds = DB::table('plans')
            ->whereIn('slug', ['plus', 'pro'])
            ->pluck('id');

        foreach ($planIds as $planId) {
            DB::table('feature_plan')->updateOrInsert([
                'plan_id' => $planId,
                'feature_id' => $featureId,
            ]);
        }
    }

    public function down(): void
    {
        $featureId = DB::table('features')->where('key', 'mock_tests')->value('id');
        if (!$featureId) {
            return;
        }

        DB::table('feature_plan')->where('feature_id', $featureId)->delete();
        DB::table('features')->where('id', $featureId)->delete();
    }
};
