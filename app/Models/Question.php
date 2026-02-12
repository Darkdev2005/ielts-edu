<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'type',
        'prompt',
        'options',
        'correct_answer',
        'ai_explanation',
        'mode',
        'meta',
    ];

    protected $casts = [
        'options' => 'array',
        'meta' => 'array',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attemptAnswers()
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function aiHelps()
    {
        return $this->hasMany(QuestionAIHelp::class);
    }
}
