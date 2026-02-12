<x-layouts.app :title="__('app.new_list')">
    <div class="flex flex-col gap-2">
        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
            {{ __('app.admin') }}
        </div>
        <h1 class="text-3xl font-semibold">{{ __('app.new_list') }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.vocabulary.store') }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        <div>
            <label class="block text-sm font-medium">{{ __('app.title') }}</label>
            <input name="title" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.cefr_level') }}</label>
            <select name="level" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                <option value="">{{ __('app.all') }}</option>
                @foreach(['A1','A2','B1','B2'] as $level)
                    <option value="{{ $level }}">{{ $level }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.description') }}</label>
            <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2"></textarea>
        </div>
        <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">
            {{ __('app.save') }}
        </button>
    </form>
</x-layouts.app>
