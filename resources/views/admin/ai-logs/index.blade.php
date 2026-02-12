<x-layouts.app :title="__('app.ai_logs')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.ai_logs') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.ai_logs_intro') }}</p>
        </div>
    </div>

    <form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 text-sm text-slate-600 shadow-sm md:grid-cols-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('app.search') }}" class="rounded-xl border border-slate-200 px-3 py-2">
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2">
            <option value="">{{ __('app.all') }}</option>
            <option value="pending" @selected(request('status') === 'pending')>{{ __('app.ai_status_running') }}</option>
            <option value="processing" @selected(request('status') === 'processing')>{{ __('app.ai_status_running') }}</option>
            <option value="done" @selected(request('status') === 'done')>{{ __('app.ai_status_success') }}</option>
            <option value="failed" @selected(request('status') === 'failed')>{{ __('app.ai_status_failed') }}</option>
            <option value="failed_quota" @selected(request('status') === 'failed_quota')>{{ __('app.ai_status_failed') }}</option>
        </select>
        <select name="type" class="rounded-xl border border-slate-200 px-3 py-2">
            <option value="">{{ __('app.all') }}</option>
            <option value="lesson_questions" @selected(request('type') === 'lesson_questions')>{{ __('app.ai_job_lesson_questions') }}</option>
            <option value="answer_explanation" @selected(request('type') === 'answer_explanation')>{{ __('app.ai_job_answer_explanation') }}</option>
            <option value="answer_explanations_batch" @selected(request('type') === 'answer_explanations_batch')>{{ __('app.ai_job_answer_explanations_batch') }}</option>
            <option value="grammar_exercises" @selected(request('type') === 'grammar_exercises')>{{ __('app.ai_job_grammar_exercises') }}</option>
            <option value="question_help" @selected(request('type') === 'question_help')>{{ __('app.ai_job_question_help') }}</option>
            <option value="grammar_answer_explanation" @selected(request('type') === 'grammar_answer_explanation')>{{ __('app.ai_job_grammar_answer_explanation') }}</option>
            <option value="grammar_exercise_help" @selected(request('type') === 'grammar_exercise_help')>{{ __('app.ai_job_grammar_exercise_help') }}</option>
            <option value="writing_feedback" @selected(request('type') === 'writing_feedback')>{{ __('app.ai_job_writing_feedback') }}</option>
            <option value="writing_followup" @selected(request('type') === 'writing_followup')>{{ __('app.ai_job_writing_followup') }}</option>
        </select>
        <select name="provider" class="rounded-xl border border-slate-200 px-3 py-2">
            <option value="">{{ __('app.all') }}</option>
            <option value="openai" @selected(request('provider') === 'openai')>OpenAI</option>
            <option value="gemini" @selected(request('provider') === 'gemini')>Gemini</option>
            <option value="cohere" @selected(request('provider') === 'cohere')>Cohere</option>
        </select>
        <div class="md:col-span-4 flex items-center gap-2">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.filter') }}</button>
            <a href="{{ route('admin.ai-logs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.reset') }}</a>
        </div>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-sm">
        <div class="grid grid-cols-12 gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase text-slate-500">
            <div class="col-span-2">{{ __('app.created') }}</div>
            <div class="col-span-2">{{ __('app.ai_job_type') }}</div>
            <div class="col-span-2">{{ __('app.ai_status') }}</div>
            <div class="col-span-2">{{ __('app.ai_provider') }}</div>
            <div class="col-span-2">{{ __('app.ai_duration') }}</div>
            <div class="col-span-2">{{ __('app.ai_user') }}</div>
        </div>
        @forelse($logs as $log)
            <div class="grid grid-cols-12 gap-4 border-b border-slate-100 px-4 py-4 text-sm text-slate-700">
                <div class="col-span-2 text-xs text-slate-500">
                    {{ $log->created_at?->format('Y-m-d H:i') }}
                </div>
                <div class="col-span-2">
                    <a href="{{ route('admin.ai-logs.show', $log) }}" class="font-semibold text-slate-900 hover:underline">
                        {{ __('app.ai_job_'.($log->input_json['task'] ?? 'question_help')) }}
                    </a>
                </div>
                <div class="col-span-2">
                    <span class="rounded-full px-2 py-1 text-xs font-semibold
                        @if($log->status === 'done') bg-emerald-100 text-emerald-700
                        @elseif(in_array($log->status, ['failed', 'failed_quota'], true)) bg-rose-100 text-rose-700
                        @else bg-amber-100 text-amber-700 @endif">
                        {{ $log->status === 'done' ? __('app.ai_status_success') : ($log->status === 'pending' || $log->status === 'processing' ? __('app.ai_status_running') : __('app.ai_status_failed')) }}
                    </span>
                </div>
                <div class="col-span-2">
                    <div class="text-sm text-slate-700">{{ $log->provider ?? '-' }}</div>
                    <div class="text-xs text-slate-400">{{ $log->model ?? '-' }}</div>
                </div>
                <div class="col-span-2 flex items-center gap-2 text-xs text-slate-500">
                    @php($duration = $log->started_at && $log->finished_at ? $log->finished_at->diffInMilliseconds($log->started_at) : null)
                    {{ $duration !== null ? $duration.' ms' : '-' }}
                    @if(in_array($log->status, ['failed', 'failed_quota'], true))
                        <form method="POST" action="{{ route('admin.ai-logs.retry', $log) }}">
                            @csrf
                            <button class="rounded-full border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700">
                                {{ __('app.ai_retry') }}
                            </button>
                        </form>
                    @endif
                </div>
                <div class="col-span-2 text-xs text-slate-500">
                    {{ $log->user?->email ?? '-' }}
                </div>
                <div class="col-span-12 text-xs text-slate-400">
                    {{ $log->input_json['prompt'] ?? '-' }}
                    @if($log->error_text)
                        <div class="mt-1 text-rose-600">{{ $log->error_text }}</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-sm text-slate-500">{{ __('app.no_logs') }}</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.app>
