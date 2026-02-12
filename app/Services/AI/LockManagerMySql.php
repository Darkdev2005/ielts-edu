<?php

namespace App\Services\AI;

use App\Models\AiLock;
use Illuminate\Support\Facades\DB;

class LockManagerMySql
{
    public function acquire(string $prefix, int $maxSlots, int $ttlSeconds = 90): ?string
    {
        $now = now();
        $lockedUntil = $now->copy()->addSeconds($ttlSeconds);

        return DB::transaction(function () use ($prefix, $maxSlots, $now, $lockedUntil) {
            for ($i = 1; $i <= $maxSlots; $i++) {
                $name = "{$prefix}_slot_{$i}";

                $lock = AiLock::where('name', $name)
                    ->lockForUpdate()
                    ->first();

                if (!$lock) {
                    AiLock::create([
                        'name' => $name,
                        'locked_until' => $lockedUntil,
                    ]);
                    return $name;
                }

                if (!$lock->locked_until || $lock->locked_until->lte($now)) {
                    $lock->update(['locked_until' => $lockedUntil]);
                    return $name;
                }
            }

            return null;
        });
    }

    public function release(string $name): void
    {
        AiLock::where('name', $name)->update(['locked_until' => null]);
    }
}
