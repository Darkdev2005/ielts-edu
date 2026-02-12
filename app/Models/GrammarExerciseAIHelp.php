<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarExerciseAIHelp extends Model
{
    use HasFactory;

    protected $table = 'grammar_exercise_ai_helps';

    protected $fillable = [
        'ai_request_id',
        'user_id',
        'grammar_exercise_id',
        'user_prompt',
        'ai_response',
        'status',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exercise()
    {
        return $this->belongsTo(GrammarExercise::class, 'grammar_exercise_id');
    }
}
