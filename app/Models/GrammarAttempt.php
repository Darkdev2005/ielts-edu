<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'grammar_topic_id',
        'score',
        'total',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(GrammarTopic::class, 'grammar_topic_id');
    }

    public function answers()
    {
        return $this->hasMany(GrammarAttemptAnswer::class, 'grammar_attempt_id');
    }
}
