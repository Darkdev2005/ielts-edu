<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrammarRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'grammar_topic_id',
        'rule_id',
        'rule_key',
        'level',
        'cefr_level',
        'rule_type',
        'title',
        'content',
        'content_json',
        'rule_text_uz',
        'rule_text_en',
        'rule_text_ru',
        'formula',
        'example_uz',
        'example_en',
        'example_ru',
        'negative_example',
        'common_mistake',
        'correct_form',
        'image_path',
        'sort_order',
    ];

    protected $casts = [
        'content_json' => 'array',
    ];

    public function topic()
    {
        return $this->belongsTo(GrammarTopic::class, 'grammar_topic_id');
    }

    public function exercises()
    {
        return $this->hasMany(GrammarExercise::class, 'grammar_rule_id');
    }

    public function scopeVisible($query)
    {
        return $query->where(function ($builder) {
            $builder->whereNull('rule_key')->orWhere('rule_key', '!=', '__unmapped__');
        });
    }

    public function getCefrLevelAttribute($value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->attributes['level'] ?? null;
    }

    public function localizedRuleText(?string $fallback = null): string
    {
        return $this->localizedField('rule_text', $fallback);
    }

    public function localizedExample(?string $fallback = null): string
    {
        return $this->localizedField('example', $fallback);
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
}
