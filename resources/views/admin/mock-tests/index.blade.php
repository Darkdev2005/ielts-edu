<x-layouts.app :title="__('app.admin').' · '.__('app.mock_tests')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.mock_tests') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.mock_tests_intro') }}</p>
        </div>
        <a href="{{ route('admin.mock-tests.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
            {{ __('app.add_mock_test') }}
        </a>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
        <a href="{{ route('admin.mock-tests.index') }}" class="rounded-full px-3 py-1 font-semibold {{ empty($module) ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.all') }}
        </a>
        <a href="{{ route('admin.mock-tests.index', ['module' => 'reading']) }}" class="rounded-full px-3 py-1 font-semibold {{ $module === 'reading' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.reading') }}
        </a>
        <a href="{{ route('admin.mock-tests.index', ['module' => 'listening']) }}" class="rounded-full px-3 py-1 font-semibold {{ $module === 'listening' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-white text-slate-600' }}">
            {{ __('app.listening') }}
        </a>
    </div>

    <div class="mt-6 space-y-4">
        @forelse($tests as $test)
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">{{ $test->title }}</div>
                    <div class="text-xs text-slate-500">
                        {{ strtoupper($test->module) }} · {{ $test->sections_count }} {{ __('app.sections') }} · {{ $test->total_questions }} {{ __('app.questions') }}
                    </div>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('admin.mock-tests.edit', $test) }}" class="hover:underline">{{ __('app.edit') }}</a>
                    <form method="POST" action="{{ route('admin.mock-tests.destroy', $test) }}" onsubmit="return confirm('Delete?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-rose-600 hover:underline">{{ __('app.delete') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('app.no_mock_tests') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $tests->links() }}</div>
</x-layouts.app>
