<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyChallengeAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_challenge_id',
        'question_id',
        'grammar_exercise_id',
        'selected_answer',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function challenge()
    {
        return $this->belongsTo(DailyChallenge::class, 'daily_challenge_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function grammarExercise()
    {
        return $this->belongsTo(GrammarExercise::class, 'grammar_exercise_id');
    }
}
