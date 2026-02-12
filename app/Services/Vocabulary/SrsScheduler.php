<?php

namespace App\Services\Vocabulary;

use App\Models\UserVocab;
use Illuminate\Support\Carbon;

class SrsScheduler
{
    public function grade(UserVocab $progress, int $quality): UserVocab
    {
        $quality = max(0, min(3, $quality));

        $ease = (float) $progress->ease_factor;
        $ease = max(1.3, $ease + (0.1 - (3 - $quality) * (0.08 + (3 - $quality) * 0.02)));

        if ($quality <= 1) {
            $progress->repetitions = 0;
            $progress->interval_days = 1;
        } else {
            $progress->repetitions += 1;
            if ($progress->repetitions === 1) {
                $progress->interval_days = 1;
            } elseif ($progress->repetitions === 2) {
                $progress->interval_days = 3;
            } else {
                $progress->interval_days = (int) round($progress->interval_days * $ease);
            }
        }

        $progress->ease_factor = $ease;
        $progress->last_reviewed_at = now();
        $progress->next_review_at = Carbon::now()->addDays($progress->interval_days);

        return $progress;
    }
}
