<?php

namespace App\Services\AI;

class WritingBandCalibrator
{
    public function calibrate(?array $parsed, string $responseText, ?string $taskType = null, ?string $difficulty = null): ?array
    {
        if (!is_array($parsed) || empty($parsed['criteria']) || !is_array($parsed['criteria'])) {
            return $parsed;
        }

        $metrics = $this->computeMetrics($responseText);
        if (!$metrics) {
            return $parsed;
        }

        $caps = [
            'task_response' => null,
            'lexical_resource' => null,
            'grammar_range_accuracy' => null,
            'overall' => null,
        ];

        if ($metrics['example_count'] < 1 && $metrics['word_count'] >= 180) {
            $caps['task_response'] = 5.0;
        }

        if ($metrics['depth_marker_count'] < 2) {
            $caps['task_response'] = min($caps['task_response'] ?? 9.0, 5.0);
        }

        if ($metrics['unique_ratio'] < 0.52) {
            $caps['lexical_resource'] = 5.0;
        } elseif ($metrics['unique_ratio'] < 0.58) {
            $caps['lexical_resource'] = 5.5;
        }

        if ($metrics['avg_sentence_len'] < 14 || $metrics['complex_ratio'] < 0.35) {
            $caps['grammar_range_accuracy'] = 5.0;
        }

        if ($metrics['grammar_error_ratio'] >= 0.02) {
            $caps['grammar_range_accuracy'] = min($caps['grammar_range_accuracy'] ?? 9.0, 5.0);
        }

        if ($metrics['grammar_error_ratio'] >= 0.035) {
            $caps['grammar_range_accuracy'] = min($caps['grammar_range_accuracy'] ?? 9.0, 4.5);
            $caps['overall'] = $caps['overall'] ? min($caps['overall'], 5.0) : 5.0;
        }

        $simpleEssay = $metrics['unique_ratio'] < 0.52
            || $metrics['avg_sentence_len'] < 14
            || $metrics['complex_ratio'] < 0.35
            || $metrics['grammar_error_ratio'] >= 0.035
            || $metrics['depth_marker_count'] < 2;
        if ($simpleEssay) {
            $caps['overall'] = 5.5;
        }

        foreach ($caps as $key => $cap) {
            if (!$cap) {
                continue;
            }
            if ($key === 'overall') {
                continue;
            }
            if (isset($parsed['criteria'][$key]['band'])) {
                $parsed['criteria'][$key]['band'] = min((float) $parsed['criteria'][$key]['band'], $cap);
            }
        }

        $overall = $this->averageCriteria($parsed['criteria']);
        if ($overall !== null) {
            $overall = $this->roundHalf($overall);
            if ($caps['overall']) {
                $overall = min($overall, $caps['overall']);
            }
            $overall = $this->applyLowCriteriaCaps($parsed['criteria'], $overall);
            $parsed['overall_band'] = $overall;
        }

        return $parsed;
    }

    private function computeMetrics(string $text): ?array
    {
        $clean = trim($text);
        if ($clean === '') {
            return null;
        }

        $words = preg_split('/[^a-zA-Z\']+/', strtolower($clean));
        $words = array_values(array_filter($words));
        $wordCount = count($words);
        if ($wordCount === 0) {
            return null;
        }

        $uniqueCount = count(array_unique($words));
        $uniqueRatio = $uniqueCount / $wordCount;

        $sentences = preg_split('/[.!?]+/', $clean);
        $sentences = array_values(array_filter(array_map('trim', $sentences)));
        $sentenceCount = max(1, count($sentences));
        $avgSentenceLen = $wordCount / $sentenceCount;

        $complexMarkers = [
            'because', 'although', 'however', 'which', 'while', 'whereas', 'despite',
            'therefore', 'moreover', 'furthermore', 'in addition', 'for example',
        ];
        $complexCount = 0;
        foreach ($sentences as $sentence) {
            $lower = strtolower($sentence);
            $hasMarker = false;
            foreach ($complexMarkers as $marker) {
                if (str_contains($lower, $marker)) {
                    $hasMarker = true;
                    break;
                }
            }
            if ($hasMarker || str_contains($sentence, ',')) {
                $complexCount++;
            }
        }
        $complexRatio = $complexCount / $sentenceCount;

        $exampleMarkers = ['for example', 'for instance', 'such as'];
        $exampleCount = 0;
        $lowerText = strtolower($clean);
        foreach ($exampleMarkers as $marker) {
            $exampleCount += substr_count($lowerText, $marker);
        }

        $depthMarkers = ['because', 'so', 'therefore', 'this means', 'as a result', 'leads to', 'which means'];
        $depthCount = 0;
        foreach ($depthMarkers as $marker) {
            $depthCount += substr_count($lowerText, $marker);
        }

        $grammarErrorCount = $this->estimateGrammarErrors($clean);
        $grammarErrorRatio = $grammarErrorCount / max(1, $wordCount);

        return [
            'word_count' => $wordCount,
            'unique_ratio' => $uniqueRatio,
            'avg_sentence_len' => $avgSentenceLen,
            'complex_ratio' => $complexRatio,
            'example_count' => $exampleCount,
            'depth_marker_count' => $depthCount,
            'grammar_error_ratio' => $grammarErrorRatio,
        ];
    }

    private function estimateGrammarErrors(string $text): int
    {
        $lower = strtolower($text);
        $patterns = [
            '/\bi\s+not\b/',
            '/\b(he|she|it)\s+(do|have|go|say|make|take|come|see|know|think|want|use|need|work|study|learn|give|find|help|feel|become)\b/',
            '/\bthey\s+is\b/',
            '/\bwe\s+is\b/',
            '/\byou\s+is\b/',
            '/\bthere\s+is\s+\w+\s+(people|students|books|cars|things)\b/',
            '/\b(a|an)\s+(students|people|children|teachers|cars|things|books)\b/',
            '/\b(no|not)\s+have\b/',
            '/\bdoesn\'t\s+have\b/',
            '/\bcan\s+be\s+watch\b/',
            '/\bnot\s+need\b/',
            '/\bnot\s+agree\b/',
            '/\bnot\s+focus\b/',
            '/\bnot\s+understand\b/',
            '/\bnot\s+do\b/',
            '/\bnot\s+study\b/',
            '/\bnot\s+enough\b/',
            '/\bstudents\s+is\b/',
            '/\bthis\s+save\b/',
            '/\bthis\s+help\b/',
            '/\bthese\s+is\b/',
            '/\bclassroom\s+is\s+still\s+need\b/',
            '/\bonline\s+learning\s+have\b/',
            '/\bthere\s+have\b/',
            '/\bpeople\s+who\s+working\b/',
            '/\bvery\s+much\s+\w+ly\b/',
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $lower, $matches)) {
                $count += count($matches[0]);
            }
        }

        return $count;
    }

    private function averageCriteria(array $criteria): ?float
    {
        $bands = [];
        foreach (['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy'] as $key) {
            if (isset($criteria[$key]['band']) && is_numeric($criteria[$key]['band'])) {
                $bands[] = (float) $criteria[$key]['band'];
            }
        }
        if (count($bands) === 0) {
            return null;
        }
        return array_sum($bands) / count($bands);
    }

    private function roundHalf(float $value): float
    {
        return round($value * 2) / 2;
    }

    private function applyLowCriteriaCaps(array $criteria, float $overall): float
    {
        $bands = [];
        foreach (['task_response', 'coherence_cohesion', 'lexical_resource', 'grammar_range_accuracy'] as $key) {
            if (isset($criteria[$key]['band']) && is_numeric($criteria[$key]['band'])) {
                $bands[] = (float) $criteria[$key]['band'];
            }
        }
        if (count($bands) < 2) {
            return $overall;
        }

        $low3 = 0;
        $low25 = 0;
        foreach ($bands as $band) {
            if ($band <= 3.0) {
                $low3++;
            }
            if ($band <= 2.5) {
                $low25++;
            }
        }

        if ($low25 >= 2) {
            return min($overall, 2.5);
        }

        if ($low3 >= 2) {
            return min($overall, 3.0);
        }

        return $overall;
    }
}
