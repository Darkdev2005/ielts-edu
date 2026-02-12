<x-layouts.app :title="__('app.mock_questions')">
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                    {{ __('app.admin') }}
                </div>
                <h1 class="mt-4 text-3xl font-semibold">{{ $test->title }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ __('app.section_number') }} {{ $section->section_number }} · {{ __('app.mock_questions') }}</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                        {{ __('app.download_sample') }}
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg">
                        <a href="{{ route('admin.mock-test-sections.questions.sample', [$test, $section]) }}?format=xlsx" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">{{ __('app.download_sample_xlsx') }}</a>
                        <a href="{{ route('admin.mock-test-sections.questions.sample', [$test, $section]) }}?format=csv" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">{{ __('app.download_sample_csv') }}</a>
                    </div>
                </details>
                <a href="{{ route('admin.mock-test-sections.questions.create', [$test, $section]) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.add_question') }}
                </a>
                <a href="{{ route('admin.mock-tests.edit', $test) }}" class="text-slate-600 hover:underline">{{ __('app.back_to_list') }}</a>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 text-sm text-slate-600 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-base font-semibold text-slate-900">{{ __('app.import_questions') }}</div>
                    <div class="mt-1 text-xs text-slate-500">{{ __('app.import_questions_help') }}</div>
                </div>
                <form method="POST" action="{{ route('admin.mock-test-sections.questions.import', [$test, $section]) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <input type="file" name="csv" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600">
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white">{{ __('app.import_csv') }}</button>
                </form>
            </div>
            <div class="mt-3 text-xs text-slate-500">
                {{ __('app.csv_columns') }}: question_type, question_text, option_a, option_b, option_c, option_d, correct_answer, order_index
                <div class="mt-1">{{ __('app.mock_correct_answer_hint') }}</div>
            </div>
        </div>

        @if(session('imported'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ __('app.import_success', ['count' => session('imported')]) }}
            </div>
        @endif

        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <div class="font-semibold">{{ __('app.import_errors') }}</div>
                <ul class="mt-2 list-disc pl-5 text-xs">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-4">
            @forelse($section->questions as $question)
                <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-semibold uppercase text-slate-500">{{ strtoupper($question->question_type) }} · #{{ $question->order_index }}</div>
                            <div class="mt-1 font-medium text-slate-900">{{ $question->question_text }}</div>
                            <div class="mt-2 text-xs text-slate-500">{{ __('app.correct_answer') }}: {{ $question->correct_answer }}</div>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('admin.mock-test-sections.questions.edit', [$test, $section, $question]) }}" class="hover:underline">{{ __('app.edit') }}</a>
                            <form method="POST" action="{{ route('admin.mock-test-sections.questions.destroy', [$test, $section, $question]) }}" onsubmit="return confirm('Delete?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-rose-600 hover:underline">{{ __('app.delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('app.no_questions') }}</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>
