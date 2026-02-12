<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVocab extends Model
{
    use HasFactory;

    protected $table = 'user_vocab';

    protected $fillable = [
        'user_id',
        'vocab_item_id',
        'repetitions',
        'interval_days',
        'ease_factor',
        'next_review_at',
        'last_reviewed_at',
    ];

    protected $casts = [
        'next_review_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'ease_factor' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function item()
    {
        return $this->belongsTo(VocabItem::class, 'vocab_item_id');
    }
}
