<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRateLimit extends Model
{
    protected $table = 'api_rate_limits';
    protected $guarded = [];

    protected $casts = [
        'window_start' => 'datetime',
    ];
}
