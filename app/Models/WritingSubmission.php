<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WritingSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_request_id',
        'user_id',
        'writing_task_id',
        'response_text',
        'word_count',
        'status',
        'band_score',
        'ai_feedback',
        'ai_feedback_json',
        'ai_error',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'ai_feedback_json' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(WritingTask::class, 'writing_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
