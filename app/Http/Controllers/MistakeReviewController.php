<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\AttemptAnswer;
use App\Services\FeatureGate;
use Illuminate\Http\Request;

class MistakeReviewController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $lessonId = $request->input('lesson');

        $answersQuery = AttemptAnswer::with(['question', 'attempt.lesson'])
            ->whereHas('attempt', fn ($q) => $q->where('user_id', $userId))
            ->where('is_correct', false);

        if ($lessonId) {
            $answersQuery->whereHas('attempt.lesson', fn ($q) => $q->where('id', $lessonId));
        }

        $answers = $answersQuery
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        $viewer = $request->user();
        $canFullExplanation = $viewer && app(FeatureGate::class)->userCan($viewer, 'ai_explanation_full');

        $answers->getCollection()->transform(function ($answer) {
            $parsed = $this->parseExplanation($answer->ai_explanation ?? $answer->question?->ai_explanation);

            $answer->setAttribute('explanation', $parsed['explanation']);
            $answer->setAttribute('explanation_title', $parsed['title']);
            $answer->setAttribute('explanation_bullets', $parsed['bullets']);
            $answer->setAttribute('explanation_tip', $parsed['tip']);
            $answer->setAttribute('explanation_has_structured', $parsed['hasStructured']);

            return $answer;
        });

        $lessons = Lesson::whereHas('attempts', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('title')
            ->get(['id', 'title', 'type']);

        return view('mistakes.index', compact('answers', 'lessons', 'lessonId', 'canFullExplanation'));
    }

    private function parseExplanation(?string $explanation): array
    {
        $explanation = trim((string) $explanation);

        if ($explanation === '') {
            return [
                'explanation' => null,
                'title' => null,
                'bullets' => [],
                'tip' => null,
                'hasStructured' => false,
            ];
        }

        $lines = preg_split('/\r\n|\r|\n/', $explanation) ?: [];
        $title = $lines ? array_shift($lines) : null;
        $bullets = [];
        $tip = null;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (stripos($line, 'Tip:') === 0) {
                $tip = trim(substr($line, 4));
                continue;
            }
            if (str_starts_with($line, '-')) {
                $bullets[] = trim(ltrim($line, '- '));
                continue;
            }
            $bullets[] = $line;
        }

        $hasStructured = !empty($bullets) || $tip !== null;

        return [
            'explanation' => $explanation,
            'title' => $title,
            'bullets' => $bullets,
            'tip' => $tip,
            'hasStructured' => $hasStructured,
        ];
    }
}
