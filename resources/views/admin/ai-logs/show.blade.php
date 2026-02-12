<x-layouts.app :title="__('app.ai_log_details')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.ai_log_details') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.ai_logs_intro') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @if(in_array($log->status, ['failed', 'failed_quota'], true))
                <form method="POST" action="{{ route('admin.ai-logs.retry', $log) }}">
                    @csrf
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        {{ __('app.ai_retry') }}
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.ai-logs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
                {{ __('app.back_to_list') }}
            </a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_job_type') }}</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ __('app.ai_job_'.($log->input_json['task'] ?? 'question_help')) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_status') }}</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">
                {{ $log->status === 'done' ? __('app.ai_status_success') : ($log->status === 'pending' || $log->status === 'processing' ? __('app.ai_status_running') : __('app.ai_status_failed')) }}
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_provider') }}</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ $log->provider ?? '-' }}</div>
            <div class="mt-1 text-xs text-slate-400">{{ $log->model ?? '-' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_user') }}</div>
            <div class="mt-1 text-lg font-semibold text-slate-900">{{ $log->user?->email ?? '-' }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_started_at') }}</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $log->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_finished_at') }}</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $log->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs text-slate-500">{{ __('app.ai_duration') }}</div>
            @php($duration = $log->started_at && $log->finished_at ? $log->finished_at->diffInMilliseconds($log->started_at) : null)
            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $duration !== null ? $duration.' ms' : '-' }}</div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="text-xs text-slate-500">{{ __('app.ai_input_summary') }}</div>
        <div class="mt-2 text-sm text-slate-700">{{ $log->input_json['prompt'] ?? '-' }}</div>
    </div>

    @if($log->error_text)
        <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700">
            <div class="text-xs font-semibold">{{ __('app.ai_error_message') }}</div>
            <div class="mt-2">{{ $log->error_text }}</div>
        </div>
    @endif

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="text-xs text-slate-500">{{ __('app.ai_meta') }}</div>
        <pre class="mt-2 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-emerald-100">{{ json_encode($log->input_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="text-xs text-slate-500">{{ __('app.ai_response') }}</div>
        <pre class="mt-2 overflow-auto rounded-xl bg-slate-950 p-4 text-xs text-emerald-100">{{ json_encode($log->output_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
</x-layouts.app>
