<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpeakingSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'speaking_prompt_id',
        'response_text',
        'word_count',
        'status',
        'band_score',
        'ai_feedback',
        'ai_feedback_json',
        'ai_error',
        'ai_request_id',
        'audio_path',
        'transcript_text',
        'has_audio',
    ];

    protected $casts = [
        'ai_feedback_json' => 'array',
        'band_score' => 'float',
        'has_audio' => 'boolean',
    ];

    public function prompt()
    {
        return $this->belongsTo(SpeakingPrompt::class, 'speaking_prompt_id');
    }
}
