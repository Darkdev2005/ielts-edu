<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'mock_section_id',
        'question_type',
        'question_text',
        'options_json',
        'correct_answer',
        'order_index',
    ];

    protected $casts = [
        'options_json' => 'array',
        'order_index' => 'integer',
    ];

    public function section()
    {
        return $this->belongsTo(MockSection::class, 'mock_section_id');
    }

    public function answers()
    {
        return $this->hasMany(MockAnswer::class);
    }
}
