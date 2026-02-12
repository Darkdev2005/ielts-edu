<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyChallenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'challenge_date',
        'question_ids',
        'grammar_exercise_id',
        'score',
        'total',
        'completed_at',
    ];

    protected $casts = [
        'challenge_date' => 'date',
        'question_ids' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grammarExercise()
    {
        return $this->belongsTo(GrammarExercise::class, 'grammar_exercise_id');
    }

    public function answers()
    {
        return $this->hasMany(DailyChallengeAnswer::class);
    }
}
