<x-layouts.app :title="__('app.user_results')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.user_results') }}</h1>
        </div>
        <a href="{{ route('admin.results.export', request()->query()) }}" class="rounded-xl border border-slate-200 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
            {{ __('app.export_csv') }}
        </a>
    </div>

    <form method="GET" action="{{ route('admin.results.index') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <label class="text-xs text-slate-500">{{ __('app.search') }}</label>
                <input name="q" value="{{ request('q') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" placeholder="{{ __('app.search_placeholder') }}">
            </div>
            <div>
                <label class="text-xs text-slate-500">{{ __('app.cefr_level') }}</label>
                <select name="cefr" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    <option value="">{{ __('app.all') }}</option>
                    @foreach(['A1','A2','B1','B2'] as $level)
                        <option value="{{ $level }}" @selected(request('cefr') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">{{ __('app.lesson_type') }}</label>
                <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                    <option value="">{{ __('app.all') }}</option>
                    <option value="reading" @selected(request('type') === 'reading')>{{ __('app.reading') }}</option>
                    <option value="listening" @selected(request('type') === 'listening')>{{ __('app.listening') }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-slate-500">{{ __('app.from') }}</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                </div>
                <div>
                    <label class="text-xs text-slate-500">{{ __('app.to') }}</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                </div>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.filter') }}</button>
            <a href="{{ route('admin.results.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.reset') }}</a>
        </div>
    </form>

    <div class="mt-8 space-y-4">
        @foreach($users as $user)
            @php
                $totalQuestions = (int) ($user->attempts_sum_total ?? 0);
                $totalScore = (int) ($user->attempts_sum_score ?? 0);
                $avg = $totalQuestions > 0 ? round(($totalScore / $totalQuestions) * 100) : 0;
            @endphp
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">{{ $user->name }}</div>
                    <div class="text-xs text-slate-500">{{ $user->email }}</div>
                </div>
                <div class="flex items-center gap-6 text-sm text-slate-600">
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.total_attempts') }}</div>
                        <div class="font-semibold text-slate-900">{{ $user->attempts_count }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.average_score') }}</div>
                        <div class="font-semibold text-slate-900">{{ $avg }}%</div>
                    </div>
                </div>
                <a href="{{ route('admin.results.show', $user) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">
                    {{ __('app.view_results') }} →
                </a>
            </div>
        @endforeach
    </div>

    <div class="mt-8">{{ $users->links() }}</div>
</x-layouts.app>
