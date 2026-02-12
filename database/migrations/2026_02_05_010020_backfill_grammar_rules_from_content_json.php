<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('grammar_rules')
            ->select([
                'id',
                'rule_type',
                'rule_text_en',
                'rule_text_ru',
                'formula',
                'example_uz',
                'example_en',
                'example_ru',
                'negative_example',
                'common_mistake',
                'correct_form',
                'content_json',
            ])
            ->whereNotNull('content_json')
            ->orderBy('id')
            ->chunkById(100, function ($rules) {
                foreach ($rules as $rule) {
                    $payload = $this->decodeJsonColumn($rule->content_json);
                    if (empty($payload)) {
                        continue;
                    }

                    $updates = [];

                    // Normalize legacy rule types into the production enum set.
                    if (trim((string) $rule->rule_type) === 'spelling') {
                        $updates['rule_type'] = 'usage';
                    }

                    // Content JSON in the legacy system is inconsistent (fields and languages are often swapped).
                    // Backfill the new atomic columns carefully, without overwriting any admin-edited values.
                    $ruleEn = $this->firstNonEmpty([
                        $payload['rule_en'] ?? null,
                        $payload['ruleEn'] ?? null,
                    ]);

                    if (trim((string) $rule->rule_text_ru) === '' && $this->looksCyrillic($ruleEn)) {
                        $updates['rule_text_ru'] = $ruleEn;
                    }

                    if (trim((string) $rule->formula) === '') {
                        $candidate = $this->firstNonEmpty([
                            $payload['rule_ru'] ?? null,
                            $payload['ruleRu'] ?? null,
                        ]);

                        if ($candidate !== '' && $this->looksLikeFormula($candidate)) {
                            $updates['formula'] = $candidate;
                        } else {
                            $fallback = $this->firstNonEmpty([
                                $payload['formula'] ?? null,
                            ]);
                            if ($fallback !== '') {
                                $updates['formula'] = $fallback;
                            }
                        }
                    }

                    if (trim((string) $rule->example_uz) === '') {
                        // Legacy imports often store Uzbek example in `example_en`.
                        $candidate = $this->firstNonEmpty([
                            $payload['example_en'] ?? null,
                            $payload['example_uz'] ?? null,
                        ]);
                        if ($candidate !== '') {
                            $updates['example_uz'] = $candidate;
                        }
                    }

                    if (trim((string) $rule->example_en) === '') {
                        // Legacy imports often store an English example sentence in `formula`.
                        $candidate = $this->firstNonEmpty([
                            $payload['formula'] ?? null,
                        ]);
                        if ($candidate !== '' && !$this->looksLikeFormula($candidate)) {
                            $updates['example_en'] = $candidate;
                        }
                    }

                    if (trim((string) $rule->negative_example) === '') {
                        $candidate = $this->firstNonEmpty([
                            $payload['example_negative'] ?? null,
                            $payload['example_nega'] ?? null,
                        ]);

                        if ($candidate === '') {
                            $legacyExampleUz = $this->firstNonEmpty([
                                $payload['example_uz'] ?? null,
                            ]);
                            if ($this->looksNegative($legacyExampleUz)) {
                                $candidate = $legacyExampleUz;
                            }
                        }

                        if ($candidate === '') {
                            $legacyFormula = $this->firstNonEmpty([
                                $payload['formula'] ?? null,
                            ]);
                            if ($this->looksNegative($legacyFormula)) {
                                $candidate = $legacyFormula;
                            }
                        }

                        if ($candidate !== '') {
                            $updates['negative_example'] = $candidate;
                        }
                    }

                    if (trim((string) $rule->common_mistake) === '') {
                        $candidate = $this->firstNonEmpty([
                            $payload['common_mistake'] ?? null,
                            $payload['common_mist'] ?? null,
                        ]);

                        // Some legacy data stores just an index in `common_mistake` (e.g. "1", "2").
                        if ($candidate !== '' && ctype_digit($candidate)) {
                            $candidate = '';
                        }

                        if ($candidate === '') {
                            $candidate = $this->firstNonEmpty([
                                $payload['signal_words'] ?? null,
                            ]);
                        }

                        if ($candidate !== '') {
                            $updates['common_mistake'] = $candidate;
                        }
                    }

                    if (trim((string) $rule->correct_form) === '') {
                        $candidate = $this->firstNonEmpty([
                            $payload['correct_form'] ?? null,
                            $payload['spelling_rule'] ?? null,
                        ]);
                        if ($candidate !== '') {
                            $updates['correct_form'] = $candidate;
                        }
                    }

                    if (!empty($updates)) {
                        $updates['updated_at'] = now();
                        DB::table('grammar_rules')->where('id', $rule->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill is intentionally not reverted.
    }

    private function decodeJsonColumn(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?: [];
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function looksCyrillic(string $text): bool
    {
        return preg_match('/[\\x{0400}-\\x{04FF}]/u', $text) === 1;
    }

    private function looksLikeFormula(string $text): bool
    {
        $lower = strtolower($text);

        return str_contains($text, '+')
            || str_contains($text, '/')
            || str_contains($lower, 'subject')
            || str_contains($lower, 'verb')
            || str_contains($lower, 'object');
    }

    private function looksNegative(string $text): bool
    {
        $lower = strtolower($text);

        return preg_match('/\\bnot\\b/u', $lower) === 1
            || str_contains($lower, "n't");
    }
};

