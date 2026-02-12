<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIGenerationLog extends Model
{
    use HasFactory;

    protected $table = 'ai_generation_logs';

    protected $fillable = [
        'user_id',
        'job_type',
        'provider',
        'model',
        'status',
        'input_summary',
        'error_message',
        'note',
        'meta',
        'started_at',
        'finished_at',
        'duration_ms',
        'retry_count',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isRateLimitError(): bool
    {
        $message = strtolower((string) ($this->error_message ?? ''));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '429')
            || str_contains($message, 'quota exceeded')
            || str_contains($message, 'retry in');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
