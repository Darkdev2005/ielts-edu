<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\GrammarAttempt;
use App\Models\SpeakingSubmission;
use App\Models\User;
use App\Models\WritingSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        [$query, $attemptFilter] = $this->buildUserQuery($request);

        $users = $query->withCount(['attempts as attempts_count' => $attemptFilter])
            ->withSum(['attempts as attempts_sum_score' => $attemptFilter], 'score')
            ->withSum(['attempts as attempts_sum_total' => $attemptFilter], 'total')
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return view('admin.results.index', compact('users'));
    }

    public function export(Request $request): StreamedResponse
    {
        [$query, $attemptFilter] = $this->buildUserQuery($request);

        $users = $query->withCount(['attempts as attempts_count' => $attemptFilter])
            ->withSum(['attempts as attempts_sum_score' => $attemptFilter], 'score')
            ->withSum(['attempts as attempts_sum_total' => $attemptFilter], 'total')
            ->orderBy('id')
            ->get();

        $filename = 'user-results-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'name',
                'email',
                'cefr_level',
                'attempts',
                'total_score',
                'total_questions',
                'avg_score_percent',
            ]);

            foreach ($users as $user) {
                $totalQuestions = (int) ($user->attempts_sum_total ?? 0);
                $totalScore = (int) ($user->attempts_sum_score ?? 0);
                $avg = $totalQuestions > 0 ? round(($totalScore / $totalQuestions) * 100) : 0;

                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->cefr_level,
                    $user->attempts_count,
                    $totalScore,
                    $totalQuestions,
                    $avg,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(Request $request, User $user)
    {
        if ($user->is_admin) {
            abort(404);
        }

        $from = $this->parseDate($request->input('from'));
        $to = $this->parseDate($request->input('to'));
        $type = $request->input('type');

        $attemptsQuery = Attempt::with('lesson')
            ->where('user_id', $user->id);

        if ($from) {
            $attemptsQuery->whereDate('completed_at', '>=', $from);
        }
        if ($to) {
            $attemptsQuery->whereDate('completed_at', '<=', $to);
        }
        if ($type) {
            $attemptsQuery->whereHas('lesson', function ($lessonQuery) use ($type) {
                $lessonQuery->where('type', $type);
            });
        }

        $attempts = $attemptsQuery
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        $grammarAttemptsQuery = GrammarAttempt::with('topic')
            ->where('user_id', $user->id);

        if ($from) {
            $grammarAttemptsQuery->whereDate('completed_at', '>=', $from);
        }
        if ($to) {
            $grammarAttemptsQuery->whereDate('completed_at', '<=', $to);
        }

        $grammarAttempts = $grammarAttemptsQuery
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'grammar_page')
            ->appends($request->query());

        $writingSubmissionsQuery = WritingSubmission::with('task')
            ->where('user_id', $user->id);

        if ($from) {
            $writingSubmissionsQuery->whereDate(DB::raw('COALESCE(submitted_at, created_at)'), '>=', $from);
        }
        if ($to) {
            $writingSubmissionsQuery->whereDate(DB::raw('COALESCE(submitted_at, created_at)'), '<=', $to);
        }

        $writingSubmissions = $writingSubmissionsQuery
            ->orderByDesc(DB::raw('COALESCE(submitted_at, created_at)'))
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'writing_page')
            ->appends($request->query());

        $speakingSubmissionsQuery = SpeakingSubmission::with('prompt')
            ->where('user_id', $user->id);

        if ($from) {
            $speakingSubmissionsQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $speakingSubmissionsQuery->whereDate('created_at', '<=', $to);
        }

        $speakingSubmissions = $speakingSubmissionsQuery
            ->latest()
            ->paginate(10, ['*'], 'speaking_page')
            ->appends($request->query());

        $stats = Attempt::where('user_id', $user->id)
            ->select(
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(score) as total_score'),
                DB::raw('SUM(total) as total_questions'),
                DB::raw('AVG(score / NULLIF(total, 0)) as avg_score')
            )
            ->first();

        return view('admin.results.show', compact(
            'user',
            'attempts',
            'stats',
            'grammarAttempts',
            'writingSubmissions',
            'speakingSubmissions'
        ));
    }

    public function attempt(User $user, Attempt $attempt)
    {
        if ($user->is_admin || $attempt->user_id !== $user->id) {
            abort(404);
        }

        $attempt->load('lesson', 'answers.question');

        return view('admin.results.attempt', compact('user', 'attempt'));
    }

    public function answersStatus(User $user, Attempt $attempt)
    {
        if ($user->is_admin || $attempt->user_id !== $user->id) {
            abort(404);
        }

        $answers = $attempt->answers()
            ->select('id', 'ai_explanation', 'is_correct')
            ->get();

        return response()->json([
            'answers' => $answers,
        ]);
    }

    public function regenerate(User $user, Attempt $attempt, AttemptAnswer $answer)
    {
        if ($user->is_admin || $attempt->user_id !== $user->id || $answer->attempt_id !== $attempt->id) {
            abort(404);
        }

        if ($answer->is_correct) {
            return redirect()->route('admin.results.attempt', [$user, $attempt]);
        }

        $answer->update(['ai_explanation' => null]);

        return redirect()
            ->route('admin.results.attempt', [$user, $attempt])
            ->with('status', 'explanation-regenerating');
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildUserQuery(Request $request): array
    {
        $query = User::where('is_admin', false);

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($cefr = $request->input('cefr')) {
            $query->where('cefr_level', $cefr);
        }

        $from = $this->parseDate($request->input('from'));
        $to = $this->parseDate($request->input('to'));
        $type = $request->input('type');

        $attemptFilter = function ($q) use ($from, $to, $type) {
            if ($from) {
                $q->whereDate('completed_at', '>=', $from);
            }
            if ($to) {
                $q->whereDate('completed_at', '<=', $to);
            }
            if ($type) {
                $q->whereHas('lesson', function ($lessonQuery) use ($type) {
                    $lessonQuery->where('type', $type);
                });
            }
        };

        if ($from || $to || $type) {
            $query->whereHas('attempts', $attemptFilter);
        }

        return [$query, $attemptFilter];
    }
}

