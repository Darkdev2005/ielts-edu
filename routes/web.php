<?php

use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\AIGenerationLogController as AdminAIGenerationLogController;
use App\Http\Controllers\Admin\AISettingsController as AdminAISettingsController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\ResultController as AdminResultController;
use App\Http\Controllers\Admin\VocabularyController as AdminVocabularyController;
use App\Http\Controllers\Admin\GrammarTopicController as AdminGrammarTopicController;
use App\Http\Controllers\Admin\GrammarRuleController as AdminGrammarRuleController;
use App\Http\Controllers\Admin\GrammarExerciseController as AdminGrammarExerciseController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\AdminUserController as AdminUserController;
use App\Http\Controllers\Admin\MockTestController as AdminMockTestController;
use App\Http\Controllers\Admin\MockSectionController as AdminMockSectionController;
use App\Http\Controllers\Admin\MockQuestionController as AdminMockQuestionController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DailyChallengeController;
use App\Http\Controllers\GrammarAttemptController;
use App\Http\Controllers\GrammarController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MistakeReviewController;
use App\Http\Controllers\QuestionAIHelpController;
use App\Http\Controllers\SpeakingController;
use App\Http\Controllers\SpeakingSubmissionController;
use App\Http\Controllers\WritingController;
use App\Http\Controllers\WritingAIHelpController;
use App\Http\Controllers\WritingSubmissionController;
use App\Http\Controllers\MockWritingController;
use App\Http\Controllers\MockSpeakingController;
use App\Http\Controllers\MockTestController;
use App\Http\Controllers\GrammarExerciseAIHelpController;
use App\Http\Controllers\GrammarTopicAIHelpController;
use App\Http\Controllers\VocabularyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\SubscriptionRequestController;
use App\Http\Controllers\Billing\SubscriptionController as BillingSubscriptionController;
use App\Http\Controllers\Billing\BillingPortalController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pricing', [PricingController::class, 'index'])->name('pricing');
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/study-plan', [DashboardController::class, 'updateStudyPlan'])->name('dashboard.study-plan.update');
    Route::get('/daily-challenge', [DailyChallengeController::class, 'show'])->name('daily-challenge.show');
    Route::post('/daily-challenge/practice-mistakes', [DailyChallengeController::class, 'practiceMistakes'])->name('daily-challenge.practice');
    Route::post('/daily-challenge/practice-submit', [DailyChallengeController::class, 'submitPractice'])->name('daily-challenge.practice.submit');
    Route::post('/daily-challenge/{challenge}', [DailyChallengeController::class, 'submit'])->name('daily-challenge.submit');

    Route::get('/lessons', [LessonController::class, 'index'])->name('lessons.index');
    Route::get('/lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::get('/lessons/{lesson}/mock', [LessonController::class, 'mock'])->name('lessons.mock');
    Route::get('/mock-tests', [MockTestController::class, 'index'])->name('mock.index');
    Route::post('/mock-tests/{mockTest}/start', [MockTestController::class, 'start'])->name('mock.start');
    Route::get('/mock-attempts/{attempt}', [MockTestController::class, 'showAttempt'])->name('mock.attempts.show');
    Route::post('/mock-attempts/{attempt}/submit', [MockTestController::class, 'submit'])->name('mock.attempts.submit');
    Route::get('/mock-attempts/{attempt}/result', [MockTestController::class, 'result'])->name('mock.attempts.result');
    Route::get('/mock-tests/writing', [MockWritingController::class, 'index'])->name('mock.writing.index');
    Route::get('/mock-tests/writing/{task}', [MockWritingController::class, 'show'])->name('mock.writing.show');
    Route::post('/mock-tests/writing/{task}/submit', [MockWritingController::class, 'submit'])->name('mock.writing.submit');
    Route::get('/mock-tests/writing/submissions/{submission}', [MockWritingController::class, 'result'])->name('mock.writing.result');
    Route::get('/mock-tests/writing/submissions/{submission}/status', [MockWritingController::class, 'status'])->name('mock.writing.status');
    Route::get('/mock-tests/speaking', [MockSpeakingController::class, 'index'])->name('mock.speaking.index');
    Route::get('/mock-tests/speaking/{prompt}', [MockSpeakingController::class, 'show'])->name('mock.speaking.show');
    Route::post('/mock-tests/speaking/{prompt}/submit', [MockSpeakingController::class, 'submit'])->name('mock.speaking.submit');
    Route::get('/mock-tests/speaking/submissions/{submission}', [MockSpeakingController::class, 'result'])->name('mock.speaking.result');
    Route::get('/mock-tests/speaking/submissions/{submission}/status', [MockSpeakingController::class, 'status'])->name('mock.speaking.status');
    Route::post('/lessons/{lesson}/attempts', [AttemptController::class, 'store'])
        ->name('attempts.store');
    Route::get('/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::get('/attempts/{attempt}/answers/status', [AttemptController::class, 'answersStatus'])->name('attempts.answers.status');
    Route::post('/questions/{question}/ai-help', [QuestionAIHelpController::class, 'store'])->name('questions.ai-help.store');
    Route::get('/ai-help/{help}', [QuestionAIHelpController::class, 'show'])->name('ai-help.show');
    Route::get('/mistakes', [MistakeReviewController::class, 'index'])->name('mistakes.index');

    Route::get('/vocabulary', [VocabularyController::class, 'index'])->name('vocabulary.index');
    Route::get('/vocabulary/translate', [VocabularyController::class, 'translatePage'])->name('vocabulary.translate.page');
    Route::get('/vocabulary/translate/quick', function () {
        return redirect()->route('vocabulary.translate.page');
    });
    Route::post('/vocabulary/translate/quick', [VocabularyController::class, 'quickTranslate'])->name('vocabulary.translate.quick');
    Route::get('/vocabulary/{list}', [VocabularyController::class, 'show'])->name('vocabulary.show');
    Route::get('/vocabulary/{list}/review', [VocabularyController::class, 'review'])->name('vocabulary.review');
    Route::post('/vocabulary/{list}/review/{item}', [VocabularyController::class, 'grade'])->name('vocabulary.grade');
    Route::post('/vocabulary/translate', [VocabularyController::class, 'translateFromForm'])->name('vocabulary.translate.form');
    Route::post('/vocabulary/{list}/translate', [VocabularyController::class, 'translate'])->name('vocabulary.translate');
    Route::post('/vocabulary/{list}/reset', [VocabularyController::class, 'reset'])->name('vocabulary.reset');

    Route::get('/grammar/attempts/{attempt}', [GrammarAttemptController::class, 'show'])->name('grammar.attempts.show');
    Route::get('/grammar/attempts/{attempt}/answers/status', [GrammarAttemptController::class, 'answersStatus'])->name('grammar.attempts.answers.status');
    Route::get('/grammar', [GrammarController::class, 'index'])->name('grammar.index');
    Route::get('/grammar/{topic}/practice', [GrammarController::class, 'practice'])->name('grammar.practice');
    Route::post('/grammar/{topic}/attempts', [GrammarAttemptController::class, 'store'])->name('grammar.attempts.store');
    Route::get('/grammar/{topic}', [GrammarController::class, 'show'])->name('grammar.show');
    Route::post('/grammar/{topic}/ai-help', [GrammarTopicAIHelpController::class, 'store'])->name('grammar.topics.ai-help.store');
    Route::get('/grammar/topic-ai-help/{help}', [GrammarTopicAIHelpController::class, 'show'])->name('grammar.topics.ai-help.show');
    Route::post('/grammar/exercises/{exercise}/ai-help', [GrammarExerciseAIHelpController::class, 'store'])->name('grammar.exercises.ai-help.store');
    Route::get('/grammar/ai-help/{help}', [GrammarExerciseAIHelpController::class, 'show'])->name('grammar.ai-help.show');

    Route::get('/writing', [WritingController::class, 'index'])->name('writing.index');
    Route::get('/writing/tasks/{task}', [WritingController::class, 'show'])->name('writing.show');
    Route::post('/writing/tasks/{task}/submit', [WritingSubmissionController::class, 'store'])->name('writing.submissions.store');
    Route::get('/writing/submissions/{submission}', [WritingSubmissionController::class, 'show'])->name('writing.submissions.show');
    Route::get('/writing/submissions/{submission}/status', [WritingSubmissionController::class, 'status'])->name('writing.submissions.status');
    Route::post('/writing/submissions/{submission}/ai-help', [WritingAIHelpController::class, 'store'])->name('writing.ai-help.store');
    Route::get('/writing/ai-help/{help}', [WritingAIHelpController::class, 'show'])->name('writing.ai-help.show');
    Route::get('/speaking', [SpeakingController::class, 'index'])->name('speaking.index');
    Route::post('/speaking/prompts/{prompt}/submit', [SpeakingSubmissionController::class, 'store'])->name('speaking.submissions.store');
    Route::get('/speaking/submissions/{submission}', [SpeakingSubmissionController::class, 'show'])->name('speaking.submissions.show');
    Route::get('/speaking/submissions/{submission}/status', [SpeakingSubmissionController::class, 'status'])->name('speaking.submissions.status');
    Route::post('/subscriptions/manual', [SubscriptionRequestController::class, 'store'])->name('subscriptions.requests.store');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::middleware('superAdmin')->group(function () {
            Route::get('/admins', [AdminUserController::class, 'index'])->name('admins.index');
            Route::patch('/admins/{user}/toggle', [AdminUserController::class, 'toggleAdmin'])->name('admins.toggle');
        });

        Route::get('/lessons', [AdminLessonController::class, 'index'])->name('lessons.index');
        Route::get('/mock-questions', [AdminLessonController::class, 'mockQuestions'])->name('mock-questions.index');
        Route::get('/mock-tests', [AdminMockTestController::class, 'index'])->name('mock-tests.index');
        Route::get('/mock-tests/create', [AdminMockTestController::class, 'create'])->name('mock-tests.create');
        Route::post('/mock-tests', [AdminMockTestController::class, 'store'])->name('mock-tests.store');
        Route::get('/mock-tests/{mockTest}/edit', [AdminMockTestController::class, 'edit'])->name('mock-tests.edit');
        Route::put('/mock-tests/{mockTest}', [AdminMockTestController::class, 'update'])->name('mock-tests.update');
        Route::delete('/mock-tests/{mockTest}', [AdminMockTestController::class, 'destroy'])->name('mock-tests.destroy');

        Route::post('/mock-tests/{mockTest}/sections', [AdminMockSectionController::class, 'store'])->name('mock-tests.sections.store');
        Route::put('/mock-tests/{mockTest}/sections/{mockSection}', [AdminMockSectionController::class, 'update'])->name('mock-tests.sections.update');
        Route::delete('/mock-tests/{mockTest}/sections/{mockSection}', [AdminMockSectionController::class, 'destroy'])->name('mock-tests.sections.destroy');

        Route::get('/mock-tests/{mockTest}/sections/{mockSection}/questions', [AdminMockQuestionController::class, 'index'])->name('mock-test-sections.questions.index');
        Route::get('/mock-tests/{mockTest}/sections/{mockSection}/questions/create', [AdminMockQuestionController::class, 'create'])->name('mock-test-sections.questions.create');
        Route::post('/mock-tests/{mockTest}/sections/{mockSection}/questions', [AdminMockQuestionController::class, 'store'])->name('mock-test-sections.questions.store');
        Route::post('/mock-tests/{mockTest}/sections/{mockSection}/questions/import', [AdminMockQuestionController::class, 'import'])->name('mock-test-sections.questions.import');
        Route::get('/mock-tests/{mockTest}/sections/{mockSection}/questions/sample', [AdminMockQuestionController::class, 'sample'])->name('mock-test-sections.questions.sample');
        Route::get('/mock-tests/{mockTest}/sections/{mockSection}/questions/export', [AdminMockQuestionController::class, 'export'])->name('mock-test-sections.questions.export');
        Route::get('/mock-tests/{mockTest}/sections/{mockSection}/questions/{mockQuestion}/edit', [AdminMockQuestionController::class, 'edit'])->name('mock-test-sections.questions.edit');
        Route::put('/mock-tests/{mockTest}/sections/{mockSection}/questions/{mockQuestion}', [AdminMockQuestionController::class, 'update'])->name('mock-test-sections.questions.update');
        Route::delete('/mock-tests/{mockTest}/sections/{mockSection}/questions/{mockQuestion}', [AdminMockQuestionController::class, 'destroy'])->name('mock-test-sections.questions.destroy');

        Route::get('/lessons/create', [AdminLessonController::class, 'create'])->name('lessons.create');
        Route::post('/lessons', [AdminLessonController::class, 'store'])->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [AdminLessonController::class, 'edit'])->name('lessons.edit');
        Route::put('/lessons/{lesson}', [AdminLessonController::class, 'update'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [AdminLessonController::class, 'destroy'])->name('lessons.destroy');

        Route::get('/lessons/{lesson}/questions', [AdminQuestionController::class, 'index'])->name('questions.index');
        Route::get('/lessons/{lesson}/questions/create', [AdminQuestionController::class, 'create'])->name('questions.create');
        Route::post('/lessons/{lesson}/questions', [AdminQuestionController::class, 'store'])->name('questions.store');
        Route::post('/lessons/{lesson}/questions/import', [AdminQuestionController::class, 'import'])->name('questions.import');
        Route::get('/lessons/{lesson}/questions/sample', [AdminQuestionController::class, 'downloadSample'])->name('questions.sample');
        Route::get('/lessons/{lesson}/questions/export', [AdminQuestionController::class, 'export'])->name('questions.export');
        Route::get('/lessons/{lesson}/questions/{question}/edit', [AdminQuestionController::class, 'edit'])->name('questions.edit');
        Route::put('/lessons/{lesson}/questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
        Route::delete('/lessons/{lesson}/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('questions.destroy');

        Route::get('/writing', [\App\Http\Controllers\Admin\WritingTaskController::class, 'index'])->name('writing.index');
        Route::get('/writing/create', [\App\Http\Controllers\Admin\WritingTaskController::class, 'create'])->name('writing.create');
        Route::post('/writing', [\App\Http\Controllers\Admin\WritingTaskController::class, 'store'])->name('writing.store');
        Route::get('/writing/{task}/edit', [\App\Http\Controllers\Admin\WritingTaskController::class, 'edit'])->name('writing.edit');
        Route::put('/writing/{task}', [\App\Http\Controllers\Admin\WritingTaskController::class, 'update'])->name('writing.update');
        Route::delete('/writing/{task}', [\App\Http\Controllers\Admin\WritingTaskController::class, 'destroy'])->name('writing.destroy');

        Route::get('/speaking-prompts', [\App\Http\Controllers\Admin\SpeakingPromptController::class, 'index'])->name('speaking-prompts.index');
        Route::get('/speaking-prompts/create', [\App\Http\Controllers\Admin\SpeakingPromptController::class, 'create'])->name('speaking-prompts.create');
        Route::post('/speaking-prompts', [\App\Http\Controllers\Admin\SpeakingPromptController::class, 'store'])->name('speaking-prompts.store');
        Route::get('/speaking-prompts/{prompt}/edit', [\App\Http\Controllers\Admin\SpeakingPromptController::class, 'edit'])->name('speaking-prompts.edit');
        Route::put('/speaking-prompts/{prompt}', [\App\Http\Controllers\Admin\SpeakingPromptController::class, 'update'])->name('speaking-prompts.update');
        Route::delete('/speaking-prompts/{prompt}', [\App\Http\Controllers\Admin\SpeakingPromptController::class, 'destroy'])->name('speaking-prompts.destroy');

        Route::get('/ai-logs', [AdminAIGenerationLogController::class, 'index'])->name('ai-logs.index');
        Route::get('/ai-logs/{log}', [AdminAIGenerationLogController::class, 'show'])->name('ai-logs.show');
        Route::post('/ai-logs/{log}/retry', [AdminAIGenerationLogController::class, 'retry'])->name('ai-logs.retry');
        Route::get('/ai-settings', [AdminAISettingsController::class, 'edit'])->name('ai-settings.edit');
        Route::post('/ai-settings', [AdminAISettingsController::class, 'update'])->name('ai-settings.update');
        Route::post('/daily-challenge/reset', [DailyChallengeController::class, 'reset'])->name('daily-challenge.reset');

        Route::get('/results', [AdminResultController::class, 'index'])->name('results.index');
        Route::get('/results/export', [AdminResultController::class, 'export'])->name('results.export');
        Route::get('/results/{user}', [AdminResultController::class, 'show'])->name('results.show');
        Route::get('/results/{user}/attempts/{attempt}', [AdminResultController::class, 'attempt'])->name('results.attempt');
        Route::get('/results/{user}/attempts/{attempt}/answers/status', [AdminResultController::class, 'answersStatus'])->name('results.answers.status');
        Route::post('/results/{user}/attempts/{attempt}/answers/{answer}/regenerate', [AdminResultController::class, 'regenerate'])->name('results.regenerate');
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions/requests/{subscriptionRequest}/approve', [AdminSubscriptionController::class, 'approveRequest'])->name('subscriptions.requests.approve');
        Route::post('/subscriptions/requests/{subscriptionRequest}/reject', [AdminSubscriptionController::class, 'rejectRequest'])->name('subscriptions.requests.reject');
        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

        Route::get('/vocabulary', [AdminVocabularyController::class, 'index'])->name('vocabulary.index');
        Route::get('/vocabulary/export', [AdminVocabularyController::class, 'exportAll'])->name('vocabulary.export');
        Route::get('/vocabulary/create', [AdminVocabularyController::class, 'create'])->name('vocabulary.create');
        Route::post('/vocabulary', [AdminVocabularyController::class, 'store'])->name('vocabulary.store');
        Route::get('/vocabulary/{list}/edit', [AdminVocabularyController::class, 'edit'])->name('vocabulary.edit');
        Route::put('/vocabulary/{list}', [AdminVocabularyController::class, 'update'])->name('vocabulary.update');
        Route::get('/vocabulary/{list}/sample', [AdminVocabularyController::class, 'downloadSample'])->name('vocabulary.sample');
        Route::post('/vocabulary/{list}/items', [AdminVocabularyController::class, 'storeItem'])->name('vocabulary.items.store');
        Route::post('/vocabulary/{list}/import', [AdminVocabularyController::class, 'importItems'])->name('vocabulary.items.import');
        Route::get('/vocabulary/{list}/export', [AdminVocabularyController::class, 'exportItems'])->name('vocabulary.items.export');
        Route::delete('/vocabulary/{list}/items/{item}', [AdminVocabularyController::class, 'destroyItem'])->name('vocabulary.items.destroy');

        Route::prefix('grammar')->name('grammar.')->group(function () {
            Route::get('/', [AdminGrammarTopicController::class, 'index'])->name('topics.index');
            Route::get('/create', [AdminGrammarTopicController::class, 'create'])->name('topics.create');
            Route::post('/', [AdminGrammarTopicController::class, 'store'])->name('topics.store');
            Route::post('/import', [AdminGrammarTopicController::class, 'importWorkbook'])->name('import');
            Route::get('/{topic}/edit', [AdminGrammarTopicController::class, 'edit'])->name('topics.edit');
            Route::put('/{topic}', [AdminGrammarTopicController::class, 'update'])->name('topics.update');
            Route::delete('/{topic}', [AdminGrammarTopicController::class, 'destroy'])->name('topics.destroy');

            Route::get('/{topic}/rules', [AdminGrammarRuleController::class, 'index'])->name('rules.index');
            Route::get('/{topic}/rules/create', [AdminGrammarRuleController::class, 'create'])->name('rules.create');
            Route::post('/{topic}/rules', [AdminGrammarRuleController::class, 'store'])->name('rules.store');
            Route::post('/{topic}/rules/import', [AdminGrammarRuleController::class, 'import'])->name('rules.import');
            Route::get('/{topic}/rules/sample', [AdminGrammarRuleController::class, 'downloadSample'])->name('rules.sample');
            Route::get('/{topic}/rules/{rule}/edit', [AdminGrammarRuleController::class, 'edit'])->name('rules.edit');
            Route::put('/{topic}/rules/{rule}', [AdminGrammarRuleController::class, 'update'])->name('rules.update');
            Route::delete('/{topic}/rules/{rule}', [AdminGrammarRuleController::class, 'destroy'])->name('rules.destroy');

            Route::get('/{topic}/exercises', [AdminGrammarExerciseController::class, 'index'])->name('exercises.index');
            Route::get('/{topic}/exercises/create', [AdminGrammarExerciseController::class, 'create'])->name('exercises.create');
            Route::post('/{topic}/exercises', [AdminGrammarExerciseController::class, 'store'])->name('exercises.store');
            Route::post('/{topic}/exercises/generate', [AdminGrammarExerciseController::class, 'generate'])->name('exercises.generate');
            Route::post('/{topic}/exercises/import', [AdminGrammarExerciseController::class, 'import'])->name('exercises.import');
            Route::get('/{topic}/exercises/sample', [AdminGrammarExerciseController::class, 'downloadSample'])->name('exercises.sample');
            Route::get('/{topic}/exercises/export', [AdminGrammarExerciseController::class, 'export'])->name('exercises.export');
            Route::get('/{topic}/exercises/{exercise}/edit', [AdminGrammarExerciseController::class, 'edit'])->name('exercises.edit');
            Route::put('/{topic}/exercises/{exercise}', [AdminGrammarExerciseController::class, 'update'])->name('exercises.update');
            Route::delete('/{topic}/exercises/{exercise}', [AdminGrammarExerciseController::class, 'destroy'])->name('exercises.destroy');
        });
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/subscribe/plus', [BillingSubscriptionController::class, 'subscribePlus'])->name('subscribe.plus');
    Route::get('/billing/portal', [BillingPortalController::class, 'redirect'])->name('billing.portal');
    Route::get('/billing/success', [BillingSubscriptionController::class, 'success'])->name('billing.success');
});

Route::get('/lang/{locale}', function (string $locale) {
    $allowed = ['en', 'uz', 'ru'];
    if (!in_array($locale, $allowed, true)) {
        abort(404);
    }

    session(['locale' => $locale]);

    $user = Auth::user();
    if ($user instanceof User) {
        $user->update(['language' => $locale]);
    }

    return back();
})->name('lang.switch');

require __DIR__.'/auth.php';
