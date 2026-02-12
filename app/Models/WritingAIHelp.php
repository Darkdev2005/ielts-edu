<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WritingAIHelp extends Model
{
    use HasFactory;

    protected $table = 'writing_ai_helps';

    protected $fillable = [
        'ai_request_id',
        'user_id',
        'writing_submission_id',
        'user_prompt',
        'ai_response',
        'status',
        'error_message',
    ];

    public function submission()
    {
        return $this->belongsTo(WritingSubmission::class, 'writing_submission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
