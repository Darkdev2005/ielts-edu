<?php

namespace App\Services;

use App\Exceptions\LimitExceededException;
use App\Models\UsageLimit;
use App\Models\User;

class UsageLimiter
{
    public function assertWithinLimit(User $user, string $limitKey): void
    {
        if ($this->shouldBypass($user)) {
            return;
        }
        $limit = $this->limitFor($limitKey);
        if ($limit === null) {
            return;
        }

        $today = now()->toDateString();
        $current = UsageLimit::where('user_id', $user->id)
            ->where('limit_key', $limitKey)
            ->where('date', $today)
            ->value('count') ?? 0;

        if ($current >= $limit) {
            throw new LimitExceededException('Daily limit reached.');
        }
    }

    public function increment(User $user, string $limitKey): void
    {
        if ($this->shouldBypass($user)) {
            return;
        }
        $limit = $this->limitFor($limitKey);
        if ($limit === null) {
            return;
        }

        $today = now()->toDateString();

        $record = UsageLimit::firstOrCreate(
            [
                'user_id' => $user->id,
                'limit_key' => $limitKey,
                'date' => $today,
            ],
            [
                'count' => 0,
            ]
        );

        $record->increment('count');
    }

    public function remaining(User $user, string $limitKey): ?int
    {
        if ($this->shouldBypass($user)) {
            return null;
        }
        $limit = $this->limitFor($limitKey);
        if ($limit === null) {
            return null;
        }

        $today = now()->toDateString();
        $current = UsageLimit::where('user_id', $user->id)
            ->where('limit_key', $limitKey)
            ->where('date', $today)
            ->value('count') ?? 0;

        return max(0, $limit - $current);
    }

    public function limitFor(string $limitKey): ?int
    {
        $value = config("subscriptions.limits.{$limitKey}");

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    private function shouldBypass(User $user): bool
    {
        return (bool) ($user->is_admin ?? false);
    }
}
