<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockAttempt extends Model
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

    private const READING_BAND_MAP = [
        ['min' => 39, 'max' => 40, 'band' => 9.0],
        ['min' => 37, 'max' => 38, 'band' => 8.5],
        ['min' => 35, 'max' => 36, 'band' => 8.0],
        ['min' => 33, 'max' => 34, 'band' => 7.5],
        ['min' => 30, 'max' => 32, 'band' => 7.0],
        ['min' => 27, 'max' => 29, 'band' => 6.5],
        ['min' => 23, 'max' => 26, 'band' => 6.0],
        ['min' => 19, 'max' => 22, 'band' => 5.5],
        ['min' => 15, 'max' => 18, 'band' => 5.0],
        ['min' => 13, 'max' => 14, 'band' => 4.5],
        ['min' => 10, 'max' => 12, 'band' => 4.0],
        ['min' => 8, 'max' => 9, 'band' => 3.5],
        ['min' => 6, 'max' => 7, 'band' => 3.0],
        ['min' => 4, 'max' => 5, 'band' => 2.5],
        ['min' => 2, 'max' => 3, 'band' => 2.0],
        ['min' => 1, 'max' => 1, 'band' => 1.0],
        ['min' => 0, 'max' => 0, 'band' => 0.0],
    ];

    protected $fillable = [
        'user_id',
        'mock_test_id',
        'started_at',
        'ended_at',
        'score_raw',
        'band_score',
        'mode',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'score_raw' => 'integer',
        'band_score' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function test()
    {
        return $this->belongsTo(MockTest::class, 'mock_test_id');
    }

    public function answers()
    {
        return $this->hasMany(MockAnswer::class);
    }

    public static function bandFromRawScore(string $module, int $score): float
    {
        $map = $module === 'reading'
            ? self::READING_BAND_MAP
            : self::LISTENING_BAND_MAP;

        $score = max(0, min(40, $score));

        foreach ($map as $row) {
            if ($score >= $row['min'] && $score <= $row['max']) {
                return (float) $row['band'];
            }
        }

        return 0.0;
    }

    public function recalculateScore(): void
    {
        $raw = $this->answers()->where('is_correct', true)->count();
        $band = self::bandFromRawScore((string) $this->test?->module, $raw);

        $this->update([
            'score_raw' => $raw,
            'band_score' => $band,
        ]);
    }
}
