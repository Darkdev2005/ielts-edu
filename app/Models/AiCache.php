<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCache extends Model
{
    protected $table = 'ai_cache';
    protected $guarded = [];

    protected $casts = [
        'response_json' => 'array',
        'expires_at' => 'datetime',
    ];
}
