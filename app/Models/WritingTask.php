<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WritingTask extends Model
{
    use HasFactory;

    public const FREE_PREVIEW_LIMIT = 2;

    protected $fillable = [
        'title',
        'task_type',
        'prompt',
        'difficulty',
        'time_limit_minutes',
        'min_words',
        'max_words',
        'is_active',
        'mode',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions()
    {
        return $this->hasMany(WritingSubmission::class);
    }

    public static function freePreviewIds(string $mode = 'practice'): array
    {
        return static::query()
            ->where('is_active', true)
            ->where('mode', $mode)
            ->orderByDesc('id')
            ->limit(self::FREE_PREVIEW_LIMIT)
            ->pluck('id')
            ->all();
    }
}
