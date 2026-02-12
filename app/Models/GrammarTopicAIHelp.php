<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarTopicAIHelp extends Model
{
    use HasFactory;

    protected $table = 'grammar_topic_ai_helps';

    protected $fillable = [
        'ai_request_id',
        'user_id',
        'grammar_topic_id',
        'user_prompt',
        'ai_response',
        'status',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(GrammarTopic::class, 'grammar_topic_id');
    }
}
