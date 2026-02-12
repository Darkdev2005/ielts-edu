<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpeakingPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'part',
        'prompt',
        'difficulty',
        'is_active',
        'mode',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'part' => 'integer',
    ];
}
