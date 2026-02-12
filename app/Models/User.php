<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Plan;
use App\Models\Subscription;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'cefr_level',
        'is_admin',
        'is_super_admin',
        'language',
        'current_plan_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function currentPlan()
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'created_by');
    }

    public function vocabProgress()
    {
        return $this->hasMany(UserVocab::class);
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->is_super_admin) {
                $user->is_admin = true;
            }
        });

        static::creating(function (User $user) {
            if (!$user->language) {
                $user->language = config('app.locale', 'uz');
            }

            if ($user->current_plan_id) {
                return;
            }

            $freePlanId = Plan::where('slug', 'free')->value('id');
            if ($freePlanId) {
                $user->current_plan_id = $freePlanId;
            }
        });
    }
}
