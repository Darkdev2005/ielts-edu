<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'grammar_topic_id',
        'grammar_rule_id',
        'exercise_id',
        'type',
        'exercise_type',
        'prompt',
        'question',
        'options',
        'correct_answer',
        'explanation',
        'explanation_uz',
        'explanation_en',
        'explanation_ru',
        'cefr_level',
        'payload_json',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'payload_json' => 'array',
    ];

    public function topic()
    {
        return $this->belongsTo(GrammarTopic::class, 'grammar_topic_id');
    }

    public function rule()
    {
        return $this->belongsTo(GrammarRule::class, 'grammar_rule_id');
    }

    public function attemptAnswers()
    {
        return $this->hasMany(GrammarAttemptAnswer::class, 'grammar_exercise_id');
    }

    public function aiHelps()
    {
        return $this->hasMany(GrammarExerciseAIHelp::class, 'grammar_exercise_id');
    }

    public function getExerciseTypeAttribute($value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->attributes['type'] ?? null;
    }

    public function setExerciseTypeAttribute($value): void
    {
        $this->attributes['exercise_type'] = $value;
        $this->attributes['type'] = $value;
    }

    public function getQuestionAttribute($value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->attributes['prompt'] ?? null;
    }

    public function setQuestionAttribute($value): void
    {
        $this->attributes['question'] = $value;
        $this->attributes['prompt'] = $value;
    }

    public function localizedExplanation(): ?string
    {
        $locale = app()->getLocale();
        $key = 'explanation_'.$locale;
        $value = $this->attributes[$key] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        $fallback = $this->attributes['explanation_uz'] ?? null;
        if (is_string($fallback) && trim($fallback) !== '') {
            return $fallback;
        }

        return $this->attributes['explanation'] ?? null;
    }
}
