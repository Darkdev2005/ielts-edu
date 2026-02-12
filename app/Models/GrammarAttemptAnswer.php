<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarAttemptAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'grammar_attempt_id',
        'grammar_exercise_id',
        'selected_answer',
        'is_correct',
        'ai_explanation',
    ];

    public function attempt()
    {
        return $this->belongsTo(GrammarAttempt::class, 'grammar_attempt_id');
    }

    public function exercise()
    {
        return $this->belongsTo(GrammarExercise::class, 'grammar_exercise_id');
    }
}
