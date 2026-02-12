<x-layouts.app :title="__('app.speaking')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.speaking') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.speaking_prompts_intro') }}</p>
        </div>
        <a href="{{ route('admin.speaking-prompts.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
            {{ __('app.add_speaking_prompt') }}
        </a>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
        <a href="{{ route('admin.speaking-prompts.index') }}" class="rounded-full px-3 py-1 font-semibold {{ empty($mode) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.all') }}
        </a>
        <a href="{{ route('admin.speaking-prompts.index', ['mode' => 'practice']) }}" class="rounded-full px-3 py-1 font-semibold {{ $mode === 'practice' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.practice_questions') }}
        </a>
        <a href="{{ route('admin.speaking-prompts.index', ['mode' => 'mock']) }}" class="rounded-full px-3 py-1 font-semibold {{ $mode === 'mock' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.mock_questions') }}
        </a>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-sm">
        <div class="grid grid-cols-12 gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase text-slate-500">
            <div class="col-span-6">{{ __('app.prompt') }}</div>
            <div class="col-span-2">{{ __('app.difficulty_label') }}</div>
            <div class="col-span-1">Part</div>
            <div class="col-span-3">{{ __('app.manage') }}</div>
        </div>
        @forelse($prompts as $prompt)
            <div class="grid grid-cols-12 gap-4 border-b border-slate-100 px-4 py-4 text-sm text-slate-700">
                <div class="col-span-6">
                    <div class="font-semibold text-slate-900">{{ \Illuminate\Support\Str::limit($prompt->prompt, 120) }}</div>
                </div>
                <div class="col-span-2">{{ $prompt->difficulty ?? '-' }}</div>
                <div class="col-span-1">{{ $prompt->part }}</div>
                <div class="col-span-3 flex items-center gap-3">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500">
                        {{ ($prompt->mode ?? 'practice') === 'mock' ? __('app.mock_questions') : __('app.practice_questions') }}
                    </span>
                    <a href="{{ route('admin.speaking-prompts.edit', $prompt) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">{{ __('app.edit') }}</a>
                    <form method="POST" action="{{ route('admin.speaking-prompts.destroy', $prompt) }}" onsubmit="return confirm('Delete?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm font-semibold text-rose-600 hover:text-rose-700">{{ __('app.delete') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-sm text-slate-500">{{ __('app.no_speaking_prompts') }}</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $prompts->links() }}</div>
</x-layouts.app>
