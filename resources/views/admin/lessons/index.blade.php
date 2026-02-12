<x-layouts.app :title="__('app.admin').' · '.__('app.lessons')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.lessons') }}</h1>
        </div>
        <a href="{{ route('admin.lessons.create') }}" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">{{ __('app.new_lesson') }}</a>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('admin.lessons.index', ['type' => 'reading']) }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.reading') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.reading') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.lessons') }}</div>
        </a>
        <a href="{{ route('admin.lessons.index', ['type' => 'listening']) }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.listening') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.listening') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.lessons') }}</div>
        </a>
        <a href="{{ route('admin.vocabulary.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.vocabulary') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.vocabulary') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.words') }}</div>
        </a>
        <a href="{{ route('admin.writing.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.writing') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.writing_tasks') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.writing_intro') }}</div>
        </a>
        <a href="{{ route('speaking.index') }}" class="rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="text-xs font-semibold uppercase text-slate-400">{{ __('app.speaking') }}</div>
            <div class="mt-2 text-base font-semibold text-slate-900">{{ __('app.speaking') }}</div>
            <div class="mt-1 text-xs text-slate-500">{{ __('app.speaking_intro') }}</div>
        </a>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-2 text-xs">
        <a href="{{ route('admin.lessons.index') }}" class="rounded-full px-3 py-1 font-semibold {{ empty($type) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.all') }}
        </a>
        <a href="{{ route('admin.lessons.index', ['type' => 'reading']) }}" class="rounded-full px-3 py-1 font-semibold {{ $type === 'reading' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.reading') }}
        </a>
        <a href="{{ route('admin.lessons.index', ['type' => 'listening']) }}" class="rounded-full px-3 py-1 font-semibold {{ $type === 'listening' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.listening') }}
        </a>
    </div>

    <div class="mt-8 space-y-4">
        @foreach($lessons as $lesson)
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">{{ $lesson->title }}</div>
                    <div class="text-xs text-slate-500">
                        {{ strtoupper($lesson->type) }} · {{ $lesson->difficulty }} · Questions: {{ $lesson->questions_count }}
                    </div>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('admin.questions.index', $lesson) }}" class="hover:underline">{{ __('app.questions') }}</a>
                    <a href="{{ route('admin.lessons.edit', $lesson) }}" class="hover:underline">{{ __('app.edit_lesson') }}</a>
                    <form method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" onsubmit="return confirm('{{ __('app.confirm_delete_lesson') }}')">
                        @csrf
                        @method('DELETE')
                        <button class="text-rose-600 hover:underline">{{ __('app.delete') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">{{ $lessons->links() }}</div>
</x-layouts.app>
