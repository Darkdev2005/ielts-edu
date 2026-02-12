<x-layouts.app :title="__('app.writing')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                {{ __('app.writing') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.writing_title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.writing_intro') }}</p>
        </div>
    </div>

    <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($tasks as $task)
            @php($isPreview = in_array($task->id, $freePreviewIds ?? [], true))
            @php($isLocked = empty($canWriting) && !$isPreview)
            @if($isLocked)
                <button type="button"
                    class="group relative rounded-2xl border border-rose-200 bg-rose-50/60 p-5 text-left shadow-sm transition hover:-translate-y-1 hover:border-rose-300 hover:shadow-lg"
                    @click="$dispatch('open-paywall', { feature: '{{ __('app.upgrade_required') }}' })"
                >
            @else
                <a href="{{ route('writing.show', $task) }}" class="group rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg">
            @endif
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $task->title }}</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs uppercase text-slate-600">{{ strtoupper($task->task_type) }}</span>
                        @if($isPreview)
                            <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ __('app.free_preview') }}</span>
                        @elseif($isLocked)
                            <span class="rounded-full bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm">PLUS</span>
                        @endif
                    </div>
                </div>
                <p class="mt-3 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($task->prompt, 120) }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700">{{ $task->difficulty }}</span>
                    @if($task->min_words)
                        <span>{{ __('app.writing_min_words_label', ['count' => $task->min_words]) }}</span>
                    @endif
                    @if($task->time_limit_minutes)
                        <span>{{ __('app.writing_time_limit_label', ['count' => $task->time_limit_minutes]) }}</span>
                    @endif
                </div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ $isLocked ? __('app.upgrade_required') : __('app.start_writing') }}</span>
                    <span class="group-hover:text-slate-700">&rarr;</span>
                </div>
            @if($isLocked)
                </button>
            @else
                </a>
            @endif
        @empty
            <p class="text-slate-500">{{ __('app.no_writing_tasks') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $tasks->links() }}</div>
</x-layouts.app>
