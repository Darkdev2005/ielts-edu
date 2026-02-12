<x-layouts.app :title="__('app.vocabulary')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.vocabulary') }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <details class="relative">
                <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white/90 px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                    {{ __('app.export_all') }}
                </summary>
                <div class="absolute right-0 z-10 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg">
                    <a href="{{ route('admin.vocabulary.export') }}?format=xlsx" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                        {{ __('app.export_all_xlsx') }}
                    </a>
                    <a href="{{ route('admin.vocabulary.export') }}?format=csv" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                        {{ __('app.export_all_csv') }}
                    </a>
                </div>
            </details>
            <a href="{{ route('admin.vocabulary.create') }}" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg">
                {{ __('app.new_list') }}
            </a>
        </div>
    </div>

    <div class="mt-8 space-y-4">
        @foreach($lists as $list)
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">{{ $list->title }}</div>
                    <div class="text-xs text-slate-500">{{ $list->items_count }} {{ __('app.words') }}</div>
                </div>
                <a href="{{ route('admin.vocabulary.edit', $list) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-900">
                    {{ __('app.edit_lesson') }}
                </a>
            </div>
        @endforeach
    </div>

    <div class="mt-8">{{ $lists->links() }}</div>
</x-layouts.app>
