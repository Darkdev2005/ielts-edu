<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'title',
        'description',
        'time_limit',
        'total_questions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'time_limit' => 'integer',
        'total_questions' => 'integer',
    ];

    public function sections()
    {
        return $this->hasMany(MockSection::class);
    }

    public function attempts()
    {
        return $this->hasMany(MockAttempt::class);
    }
}
