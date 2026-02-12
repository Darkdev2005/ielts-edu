<?php

namespace App\Services\AI;

use App\Models\ApiRateLimit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RateLimiterMySql
{
    public function hit(string $scope, string $scopeKey, int $limit, ?Carbon $now = null): bool
    {
        $now = $now ?: now();
        $windowStart = $now->copy()->startOfMinute();

        return DB::transaction(function () use ($scope, $scopeKey, $limit, $windowStart) {
            /** @var ApiRateLimit|null $row */
            $row = ApiRateLimit::where('scope', $scope)
                ->where('scope_key', $scopeKey)
                ->where('window_start', $windowStart)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                ApiRateLimit::create([
                    'scope' => $scope,
                    'scope_key' => $scopeKey,
                    'window_start' => $windowStart,
                    'count' => 1,
                ]);
                return true;
            }

            if ($row->count >= $limit) {
                return false;
            }

            $row->increment('count');
            return true;
        });
    }
}
