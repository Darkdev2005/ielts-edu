<x-layouts.app :title="$test->title">
    @php($audioMaxMb = max(1, (int) config('mock.audio_max_mb', 15)))
    <div class="mx-auto max-w-5xl space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                    {{ __('app.admin') }}
                </div>
                <h1 class="mt-4 text-3xl font-semibold">{{ $test->title }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ strtoupper($test->module) }}</p>
            </div>
            <a href="{{ route('admin.mock-tests.index') }}" class="text-sm text-slate-600 hover:underline">{{ __('app.back_to_list') }}</a>
        </div>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <div class="font-semibold">{{ __('app.fix_following') }}</div>
                <ul class="mt-2 list-disc pl-5 text-xs">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.mock-tests.update', $test) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            @csrf
            @method('PUT')
            @include('admin.mock-tests.partials.form', ['test' => $test])
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">{{ __('app.update') }}</button>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('app.sections') }}</h2>
                <span class="text-xs text-slate-500">{{ $test->sections->count() }}</span>
            </div>

            <form method="POST" action="{{ route('admin.mock-tests.sections.store', $test) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-b border-slate-100 pb-4 md:grid-cols-4">
                @csrf
                <input type="number" name="section_number" min="1" max="{{ $test->module === 'listening' ? 4 : 3 }}" placeholder="{{ __('app.section_number') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
                <input type="text" name="title" placeholder="{{ __('app.title') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                @if($test->module === 'listening')
                    <div class="space-y-2 md:col-span-2">
                        <input type="url" name="audio_url" placeholder="{{ __('app.audio_url') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <input type="file" name="audio_file" accept="audio/*" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <div class="mt-1 text-xs text-slate-500">{{ __('app.mock_listening_audio_direct_only') }}</div>
                        <div class="text-xs text-slate-500">{{ __('app.mock_listening_audio_upload_hint', ['size' => $audioMaxMb]) }}</div>
                    </div>
                @else
                    <textarea name="passage_text" rows="2" placeholder="{{ __('app.reading_content') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm md:col-span-2" required></textarea>
                @endif
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('app.create') }}</button>
            </form>

            <div class="mt-4 space-y-3">
                @forelse($test->sections as $section)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <form method="POST" action="{{ route('admin.mock-tests.sections.update', [$test, $section]) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-4">
                            @csrf
                            @method('PUT')
                            <input type="number" name="section_number" min="1" max="{{ $test->module === 'listening' ? 4 : 3 }}" value="{{ $section->section_number }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm" required>
                            <input type="text" name="title" value="{{ $section->title }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            @if($test->module === 'listening')
                                <div class="space-y-2 md:col-span-2">
                                    <input type="url" name="audio_url" value="{{ $section->audio_url }}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <input type="file" name="audio_file" accept="audio/*" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                    <div class="mt-1 text-xs text-slate-500">{{ __('app.mock_listening_audio_direct_only') }}</div>
                                    <div class="text-xs text-slate-500">{{ __('app.mock_listening_audio_upload_hint', ['size' => $audioMaxMb]) }}</div>
                                </div>
                            @else
                                <textarea name="passage_text" rows="2" class="rounded-lg border border-slate-200 px-3 py-2 text-sm md:col-span-2" required>{{ $section->passage_text }}</textarea>
                            @endif
                            <div class="flex items-center gap-3 text-sm">
                                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">{{ __('app.update') }}</button>
                                <a href="{{ route('admin.mock-test-sections.questions.index', [$test, $section]) }}" class="text-slate-700 hover:underline">
                                    {{ __('app.questions') }} ({{ $section->questions_count }})
                                </a>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('admin.mock-tests.sections.destroy', [$test, $section]) }}" class="mt-2 text-right" onsubmit="return confirm('Delete section?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-semibold text-rose-600 hover:underline">{{ __('app.delete') }}</button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('app.no_mock_sections') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.app>
