<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    private const CEFR_ORDER = [
        'A1' => 1,
        'A2' => 2,
        'B1' => 3,
        'B2' => 4,
        'C1' => 5,
        'C2' => 6,
    ];

    protected $fillable = [
        'title',
        'type',
        'content_text',
        'audio_url',
        'mock_content_text',
        'mock_audio_url',
        'difficulty',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function requiredFeatureKey(): ?string
    {
        $level = strtoupper((string) $this->difficulty);
        $rank = self::CEFR_ORDER[$level] ?? null;

        if ($rank === null) {
            return null;
        }

        if ($rank >= self::CEFR_ORDER['B2']) {
            return match ($this->type) {
                'reading' => 'reading_pro',
                'listening' => 'listening_pro',
                default => null,
            };
        }

        if ($rank >= self::CEFR_ORDER['B1']) {
            return match ($this->type) {
                'reading' => 'reading_full',
                'listening' => 'listening_full',
                default => null,
            };
        }

        return null;
    }

    public function requiredPlanLabel(): ?string
    {
        $featureKey = $this->requiredFeatureKey();

        if (in_array($featureKey, ['reading_pro', 'listening_pro'], true)) {
            return 'PRO';
        }

        if (in_array($featureKey, ['reading_full', 'listening_full'], true)) {
            return 'PLUS';
        }

        return null;
    }
}
