<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserStudyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start_date',
        'lessons_target',
        'grammar_target',
        'vocab_target',
    ];

    protected $casts = [
        'week_start_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
