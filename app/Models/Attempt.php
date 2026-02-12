<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attempt extends Model
{
    use HasFactory;

    private const LISTENING_BAND_MAP = [
        ['min' => 39, 'max' => 40, 'band' => 9.0],
        ['min' => 37, 'max' => 38, 'band' => 8.5],
        ['min' => 35, 'max' => 36, 'band' => 8.0],
        ['min' => 32, 'max' => 34, 'band' => 7.5],
        ['min' => 30, 'max' => 31, 'band' => 7.0],
        ['min' => 26, 'max' => 29, 'band' => 6.5],
        ['min' => 23, 'max' => 25, 'band' => 6.0],
        ['min' => 18, 'max' => 22, 'band' => 5.5],
        ['min' => 16, 'max' => 17, 'band' => 5.0],
        ['min' => 13, 'max' => 15, 'band' => 4.5],
        ['min' => 11, 'max' => 12, 'band' => 4.0],
        ['min' => 8, 'max' => 10, 'band' => 3.5],
        ['min' => 6, 'max' => 7, 'band' => 3.0],
        ['min' => 4, 'max' => 5, 'band' => 2.5],
        ['min' => 2, 'max' => 3, 'band' => 2.0],
        ['min' => 1, 'max' => 1, 'band' => 1.0],
        ['min' => 0, 'max' => 0, 'band' => 0.0],
    ];

    protected $fillable = [
        'user_id',
        'lesson_id',
        'score',
        'total',
        'status',
        'mode',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function answers()
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function listeningMockRawScore(): ?int
    {
        if (($this->lesson?->type ?? null) !== 'listening') {
            return null;
        }

        if (($this->mode ?? 'practice') !== 'mock') {
            return null;
        }

        if ($this->total <= 0) {
            return null;
        }

        $scaled = (int) round(($this->score / $this->total) * 40);
        return max(0, min(40, $scaled));
    }

    public function listeningMockBandScore(): ?float
    {
        $rawScore = $this->listeningMockRawScore();
        if ($rawScore === null) {
            return null;
        }

        return self::listeningBandFromRawScore($rawScore);
    }

    public static function listeningBandFromRawScore(int $score): float
    {
        $score = max(0, min(40, $score));
        foreach (self::LISTENING_BAND_MAP as $row) {
            if ($score >= $row['min'] && $score <= $row['max']) {
                return (float) $row['band'];
            }
        }

        return 0.0;
    }
}
