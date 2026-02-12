<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'price_monthly',
        'is_active',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'feature_plan')->withTimestamps();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
