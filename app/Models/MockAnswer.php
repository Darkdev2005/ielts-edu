<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'mock_attempt_id',
        'mock_question_id',
        'user_answer',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function attempt()
    {
        return $this->belongsTo(MockAttempt::class, 'mock_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(MockQuestion::class, 'mock_question_id');
    }
}
