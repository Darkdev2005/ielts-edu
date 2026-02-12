<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLock extends Model
{
    protected $table = 'ai_locks';
    protected $primaryKey = 'name';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'locked_until' => 'datetime',
    ];
}
