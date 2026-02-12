<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionAIHelp extends Model
{
    use HasFactory;

    protected $table = 'question_ai_helps';

    protected $fillable = [
        'ai_request_id',
        'user_id',
        'question_id',
        'user_prompt',
        'ai_response',
        'status',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function messages()
    {
        return $this->hasMany(QuestionAIHelpMessage::class, 'question_ai_help_id');
    }
}
