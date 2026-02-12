<x-layouts.app :title="__('app.admin').' · '.__('app.mock_questions_nav')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.mock_questions_nav') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.mock_questions_intro') }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.mock-tests.index', ['module' => 'reading']) }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.reading') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.mock_questions') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $readingMockCount }} {{ __('app.questions') }}</div>
        </a>
        <a href="{{ route('admin.mock-tests.index', ['module' => 'listening']) }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.listening') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.mock_questions') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $listeningMockCount }} {{ __('app.questions') }}</div>
        </a>
        <a href="{{ route('admin.writing.index', ['mode' => 'mock']) }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.mock_questions') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $writingMockCount }} {{ __('app.writing_tasks') }}</div>
        </a>
        <a href="{{ route('admin.speaking-prompts.index', ['mode' => 'mock']) }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.speaking') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.mock_questions') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ $speakingMockCount }} {{ __('app.questions') }}</div>
        </a>
    </div>

    
</x-layouts.app>
