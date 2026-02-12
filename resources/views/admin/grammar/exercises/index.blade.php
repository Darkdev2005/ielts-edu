<x-layouts.app :title="__('app.exercises')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.exercises') }}</h1>
            <p class="text-sm text-slate-500">{{ $topic->title }}</p>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white/90 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                    {{ __('app.download_sample') }}
                </summary>
                <div class="absolute right-0 z-10 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg">
                    <a href="{{ route('admin.grammar.exercises.sample', $topic) }}?format=xlsx" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                        {{ __('app.download_sample_xlsx') }}
                    </a>
                    <a href="{{ route('admin.grammar.exercises.sample', $topic) }}?format=csv" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                        {{ __('app.download_sample_csv') }}
                    </a>
                </div>
            </details>
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white/90 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                    {{ __('app.export') }}
                </summary>
                <div class="absolute right-0 z-10 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg">
                    <a href="{{ route('admin.grammar.exercises.export', $topic) }}?format=xlsx" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                        {{ __('app.export_xlsx') }}
                    </a>
                    <a href="{{ route('admin.grammar.exercises.export', $topic) }}?format=csv" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                        {{ __('app.export_csv') }}
                    </a>
                </div>
            </details>
            <a href="{{ route('admin.grammar.exercises.create', $topic) }}" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg">{{ __('app.add_exercise') }}</a>
            <a href="{{ route('admin.grammar.topics.index') }}" class="hover:underline">{{ __('app.back_to_list') }}</a>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 text-sm text-slate-600 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-base font-semibold text-slate-900">{{ __('app.import_exercises') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.import_exercises_help') }}</div>
            </div>
            <form method="POST" action="{{ route('admin.grammar.exercises.import', $topic) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="csv" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ __('app.import_csv') }}</button>
            </form>
        </div>

        <div class="mt-3 text-xs text-slate-500">
            {{ __('app.csv_columns') }}: topic_key, rule_key (yoki rule_id), exercise_id, exercise_type, question, option_a, option_b, option_c, option_d, correct_answer, explanation_uz, explanation_en, explanation_ru, cefr_level, sort_order
        </div>
        <div class="mt-1 text-xs text-slate-400">
            Legacy: prompt, option_a, option_b, option_c, option_d, correct_answer, explanation, sort_order
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-white/95 p-5 text-sm text-slate-600 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-base font-semibold text-slate-900">{{ __('app.generate_exercises') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.generate_exercises_help') }}</div>
            </div>
            <form method="POST" action="{{ route('admin.grammar.exercises.generate', $topic) }}" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="number" name="count" value="10" min="1" max="50" class="w-24 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ __('app.generate') }}</button>
            </form>
        </div>
    </div>

    @if(session('status'))
        <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
            {{ session('status') }}
        </div>
    @endif

    @if(session('imported'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ __('app.import_success', ['count' => session('imported')]) }}
        </div>
    @endif

    @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            <div class="font-semibold">{{ __('app.import_errors') }}</div>
            <ul class="mt-2 list-disc pl-5 text-xs">
                @foreach(session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8 space-y-4">
        @forelse($topic->exercises as $exercise)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div class="font-medium">
                        {{ $exercise->question ?? $exercise->prompt }}
                        @if($exercise->rule?->rule_key)
                            <span class="ml-2 text-xs text-slate-400">{{ $exercise->rule->rule_key }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <a href="{{ route('admin.grammar.exercises.edit', [$topic, $exercise]) }}" class="hover:underline">{{ __('app.edit') }}</a>
                        <form method="POST" action="{{ route('admin.grammar.exercises.destroy', [$topic, $exercise]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">{{ __('app.delete') }}</button>
                        </form>
                    </div>
                </div>
                <div class="mt-2 text-sm text-slate-600">
                    {{ __('app.exercise_type') }}: {{ strtoupper($exercise->exercise_type ?? $exercise->type ?? 'mcq') }}
                    · {{ __('app.correct') }}: {{ $exercise->correct_answer }}
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 text-sm text-slate-600 shadow-sm">
                {{ __('app.no_exercises') }}
            </div>
        @endforelse
    </div>
</x-layouts.app>
