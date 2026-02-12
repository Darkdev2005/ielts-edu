<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MockSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'mock_test_id',
        'section_number',
        'title',
        'audio_url',
        'audio_disk',
        'audio_path',
        'passage_text',
        'question_count',
    ];

    protected $casts = [
        'section_number' => 'integer',
        'question_count' => 'integer',
    ];

    public function test()
    {
        return $this->belongsTo(MockTest::class, 'mock_test_id');
    }

    public function questions()
    {
        return $this->hasMany(MockQuestion::class);
    }
}
