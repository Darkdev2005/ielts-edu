<x-layouts.app :title="__('app.ai_settings')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.ai_settings') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.ai_settings_intro') }}</p>
        </div>
        <a href="{{ route('admin.ai-logs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            {{ __('app.ai_logs') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.ai-settings.update') }}" class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white/95 p-6 text-sm text-slate-700 shadow-sm md:grid-cols-2">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-slate-500">{{ __('app.ai_retry_interval') }}</label>
            <input type="number" name="retry_interval_minutes" value="{{ old('retry_interval_minutes', $values['retry_interval_minutes']) }}" min="1" max="60" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            <div class="mt-1 text-xs text-slate-400">{{ __('app.ai_retry_interval_help') }}</div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">{{ __('app.ai_retry_limit') }}</label>
            <input type="number" name="retry_limit" value="{{ old('retry_limit', $values['retry_limit']) }}" min="1" max="200" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            <div class="mt-1 text-xs text-slate-400">{{ __('app.ai_retry_limit_help') }}</div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">{{ __('app.ai_retry_min_age') }}</label>
            <input type="number" name="retry_min_age_minutes" value="{{ old('retry_min_age_minutes', $values['retry_min_age_minutes']) }}" min="1" max="120" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            <div class="mt-1 text-xs text-slate-400">{{ __('app.ai_retry_min_age_help') }}</div>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500">{{ __('app.ai_retry_max_attempts') }}</label>
            <input type="number" name="retry_max_attempts" value="{{ old('retry_max_attempts', $values['retry_max_attempts']) }}" min="1" max="10" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2">
            <div class="mt-1 text-xs text-slate-400">{{ __('app.ai_retry_max_attempts_help') }}</div>
        </div>
        <div class="md:col-span-2">
            <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">
                {{ __('app.save') }}
            </button>
        </div>
    </form>
</x-layouts.app>
