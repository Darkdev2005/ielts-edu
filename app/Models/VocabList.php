<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabList extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'level',
        'description',
        'created_by',
    ];

    public function items()
    {
        return $this->hasMany(VocabItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
