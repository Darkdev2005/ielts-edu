<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'limit_key',
        'date',
        'count',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $dateFormat = 'Y-m-d';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
