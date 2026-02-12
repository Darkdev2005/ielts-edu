<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'topic_key',
        'title_uz',
        'title_en',
        'title_ru',
        'description',
        'description_uz',
        'description_en',
        'description_ru',
        'cefr_level',
        'sort_order',
        'created_by',
    ];

    public function getTitleAttribute($value): string
    {
        return $this->localizedField('title', $value);
    }

    public function getDescriptionAttribute($value): ?string
    {
        $text = $this->localizedField('description', $value);
        return $text !== '' ? $text : null;
    }

    private function localizedField(string $base, ?string $fallback = null): string
    {
        $locale = app()->getLocale();

        $localeValue = trim((string) $this->rawAttribute("{$base}_{$locale}"));
        if ($localeValue !== '') {
            return $localeValue;
        }

        $uzValue = trim((string) $this->rawAttribute("{$base}_uz"));
        if ($uzValue !== '') {
            return $uzValue;
        }

        $enValue = trim((string) $this->rawAttribute("{$base}_en"));
        if ($enValue !== '') {
            return $enValue;
        }

        $ruValue = trim((string) $this->rawAttribute("{$base}_ru"));
        if ($ruValue !== '') {
            return $ruValue;
        }

        $fallbackValue = trim((string) $fallback);
        return $fallbackValue !== '' ? $fallbackValue : '';
    }

    private function rawAttribute(string $key): ?string
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rules()
    {
        return $this->hasMany(GrammarRule::class);
    }

    public function exercises()
    {
        return $this->hasMany(GrammarExercise::class);
    }

    public function attempts()
    {
        return $this->hasMany(GrammarAttempt::class);
    }

    public function aiHelps()
    {
        return $this->hasMany(GrammarTopicAIHelp::class, 'grammar_topic_id');
    }
}
