<x-layouts.app :title="$list->title">
    <div class="flex flex-col gap-2">
        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
            {{ __('app.admin') }}
        </div>
        <h1 class="text-3xl font-semibold">{{ $list->title }}</h1>
        <p class="text-sm text-slate-500">{{ $list->description }}</p>
    </div>

    <form method="POST" action="{{ route('admin.vocabulary.update', $list) }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium">{{ __('app.title') }}</label>
            <input name="title" value="{{ old('title', $list->title) }}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2" required>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.cefr_level') }}</label>
            <select name="level" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">
                <option value="">{{ __('app.all') }}</option>
                @foreach(['A1','A2','B1','B2'] as $level)
                    <option value="{{ $level }}" @selected(old('level', $list->level) === $level)>{{ $level }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('app.description') }}</label>
            <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2">{{ old('description', $list->description) }}</textarea>
        </div>
        <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">
            {{ __('app.update') }}
        </button>
    </form>

    <div class="mt-10 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <h2 class="text-lg font-semibold">{{ __('app.words') }}</h2>
        @if(session('status') === 'import-failed')
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ __('app.import_failed') }}
            </div>
        @endif
        @if(is_string(session('status')) && str_starts_with(session('status'), 'imported:'))
            @php($parts = explode(':', session('status')))
            @php($created = $parts[1] ?? 0)
            @php($updated = $parts[2] ?? 0)
            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ __('app.import_success') }} ·
                {{ __('app.created') }}: {{ $created }},
                {{ __('app.updated') }}: {{ $updated }}
            </div>
        @endif
        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                <div class="font-semibold">{{ __('app.import_errors') }}</div>
                <ul class="mt-2 list-disc pl-5 text-xs">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-semibold text-slate-800">{{ __('app.import_csv') }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ __('app.csv_format_help') }}</div>
            </div>
            <div class="flex items-center gap-2">
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                        {{ __('app.download_sample') }}
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg">
                        <a href="{{ route('admin.vocabulary.sample', $list) }}?format=xlsx" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                            {{ __('app.download_sample_xlsx') }}
                        </a>
                        <a href="{{ route('admin.vocabulary.sample', $list) }}?format=csv" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                            {{ __('app.download_sample_csv') }}
                        </a>
                    </div>
                </details>
                <details class="relative">
                    <summary class="cursor-pointer list-none rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-white">
                        {{ __('app.export') }}
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg">
                        <a href="{{ route('admin.vocabulary.items.export', $list) }}?format=xlsx" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                            {{ __('app.export_xlsx') }}
                        </a>
                        <a href="{{ route('admin.vocabulary.items.export', $list) }}?format=csv" class="block rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-50">
                            {{ __('app.export_csv') }}
                        </a>
                    </div>
                </details>
            </div>
        </div>
        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <form method="POST" action="{{ route('admin.vocabulary.items.import', $list) }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-3 md:flex-row md:items-center">
                @csrf
                <input type="file" name="csv" accept=".csv,.xls,.xlsx,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2">
                <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                    {{ __('app.upload_csv') }}
                </button>
            </form>
            <div class="mt-2 text-xs text-slate-400">
                term,pronunciation,definition,definition_uz,definition_ru,part_of_speech,example
            </div>
        </div>
        <form method="POST" action="{{ route('admin.vocabulary.items.store', $list) }}" class="mt-4 grid gap-3 md:grid-cols-4">
            @csrf
            <input name="term" placeholder="{{ __('app.term') }}" class="rounded-xl border border-slate-200 px-3 py-2" required>
            <input name="pronunciation" placeholder="{{ __('app.pronunciation') }}" class="rounded-xl border border-slate-200 px-3 py-2">
            <input name="definition" placeholder="{{ __('app.definition') }} (EN)" class="rounded-xl border border-slate-200 px-3 py-2">
            <input name="definition_uz" placeholder="{{ __('app.definition') }} (UZ)" class="rounded-xl border border-slate-200 px-3 py-2">
            <input name="definition_ru" placeholder="{{ __('app.definition') }} (RU)" class="rounded-xl border border-slate-200 px-3 py-2">
            <input name="part_of_speech" placeholder="{{ __('app.part_of_speech') }}" class="rounded-xl border border-slate-200 px-3 py-2">
            <input name="example" placeholder="{{ __('app.example') }}" class="rounded-xl border border-slate-200 px-3 py-2">
            <div class="md:col-span-4">
                <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">
                    {{ __('app.add_word') }}
                </button>
            </div>
        </form>

        <div class="mt-6 space-y-3">
            @foreach($list->items as $item)
                <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold text-slate-900">{{ $item->term }}</div>
                        @if($item->pronunciation)
                            <div class="text-xs text-slate-500">{{ $item->pronunciation }}</div>
                        @endif
                        <div class="text-xs text-slate-500">{{ $item->definition }}</div>
                        @if($item->definition_uz)
                            <div class="text-xs text-slate-400">UZ: {{ $item->definition_uz }}</div>
                        @endif
                        @if($item->definition_ru)
                            <div class="text-xs text-slate-400">RU: {{ $item->definition_ru }}</div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.vocabulary.items.destroy', [$list, $item]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="text-sm text-rose-600 hover:underline">{{ __('app.delete') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.app>
