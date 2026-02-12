<x-layouts.app :title="__('app.grammar')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.grammar_topics') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.manage_grammar') }}</p>
        </div>
        <a href="{{ route('admin.grammar.topics.create') }}" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg">
            {{ __('app.new_topic') }}
        </a>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-5 text-sm text-slate-600 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-base font-semibold text-slate-900">{{ __('app.import_workbook') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.import_workbook_help') }}</div>
            </div>
            <form method="POST" action="{{ route('admin.grammar.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="xlsx" accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ __('app.import_workbook') }}</button>
            </form>
        </div>
        <div class="mt-3 text-xs text-slate-500">
            {{ __('app.import_workbook_sheets') }}
        </div>
    </div>

    @if(session('imported_workbook'))
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ __('app.import_workbook_success', session('imported_workbook')) }}
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
        @forelse($topics as $topic)
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-lg font-semibold text-slate-900">{{ $topic->title }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ __('app.rules') }}: {{ $topic->rules_count }} · {{ __('app.exercises') }}: {{ $topic->exercises_count }}
                            @if($topic->cefr_level)
                                · {{ $topic->cefr_level }}
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <a href="{{ route('admin.grammar.rules.index', $topic) }}" class="hover:underline">{{ __('app.manage_rules') }}</a>
                        <a href="{{ route('admin.grammar.exercises.index', $topic) }}" class="hover:underline">{{ __('app.manage_exercises') }}</a>
                        <a href="{{ route('admin.grammar.topics.edit', $topic) }}" class="hover:underline">{{ __('app.edit') }}</a>
                        <form method="POST" action="{{ route('admin.grammar.topics.destroy', $topic) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">{{ __('app.delete') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 text-sm text-slate-600 shadow-sm">
                {{ __('app.no_grammar_topics') }}
            </div>
        @endforelse
    </div>
</x-layouts.app>
