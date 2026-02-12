<x-layouts.app :title="$lesson->title">
    <div class="flex flex-col gap-2">
        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
            {{ __('app.admin') }}
        </div>
        <h1 class="text-3xl font-semibold">{{ __('app.edit_lesson') }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.lessons.update', $lesson) }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('admin.lessons.partials.form', ['lesson' => $lesson])
        <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">{{ __('app.update') }}</button>
    </form>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="font-medium">{{ __('app.questions') }}</h2>
            <a href="{{ route('admin.questions.index', $lesson) }}" class="text-sm hover:underline">{{ __('app.manage') }}</a>
        </div>
        <p class="mt-2 text-sm text-slate-500">{{ __('app.generated') }}: {{ $lesson->questions->count() }}</p>
    </div>
</x-layouts.app>
