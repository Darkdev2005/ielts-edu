<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vocab_list_id',
        'term',
        'pronunciation',
        'definition',
        'definition_uz',
        'definition_ru',
        'example',
        'part_of_speech',
    ];

    public function list()
    {
        return $this->belongsTo(VocabList::class, 'vocab_list_id');
    }

    public function progress()
    {
        return $this->hasMany(UserVocab::class);
    }
}
