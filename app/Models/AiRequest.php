<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;

class AiRequest extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'input_json' => 'array',
        'output_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isQuotaError(): bool
    {
        $message = strtolower((string) $this->error_text);
        return $message !== '' && (
            str_contains($message, 'quota exceeded')
            || str_contains($message, 'generate_content_free_tier_requests')
            || str_contains($message, 'billing')
            || str_contains($message, 'plan')
        );
    }

    public function isStuckPending(int $seconds): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        if ($seconds <= 0 || !$this->created_at) {
            return false;
        }

        return $this->started_at === null
            && $this->created_at->diffInSeconds(now()) > $seconds;
    }
}
