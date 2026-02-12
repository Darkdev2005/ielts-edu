<x-layouts.app :title="__('app.add_speaking_prompt')">
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.add_speaking_prompt') }}</h1>
        </div>
        <a href="{{ route('admin.speaking-prompts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.speaking-prompts.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @include('admin.speaking-prompts._form')
        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">{{ __('app.save') }}</button>
            <a href="{{ route('admin.speaking-prompts.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
