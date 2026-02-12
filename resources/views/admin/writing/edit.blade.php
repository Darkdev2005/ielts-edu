<x-layouts.app :title="__('app.edit_writing_task')">
    <div class="flex items-center justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.edit_writing_task') }}</h1>
        </div>
        <a href="{{ route('admin.writing.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.back_to_list') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.writing.update', $task) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @method('PUT')
        @include('admin.writing._form', ['task' => $task])
        <div class="mt-6 flex items-center gap-3">
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">{{ __('app.update') }}</button>
            <a href="{{ route('admin.writing.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('app.cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
