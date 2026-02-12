<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderState extends Model
{
    protected $fillable = [
        'provider',
        'cooldown_until',
    ];

    protected $casts = [
        'cooldown_until' => 'datetime',
    ];
}
