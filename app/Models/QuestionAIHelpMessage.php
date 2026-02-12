<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionAIHelpMessage extends Model
{
    use HasFactory;

    protected $table = 'question_ai_help_messages';

    protected $fillable = [
        'question_ai_help_id',
        'role',
        'content',
    ];

    public function help()
    {
        return $this->belongsTo(QuestionAIHelp::class, 'question_ai_help_id');
    }
}
