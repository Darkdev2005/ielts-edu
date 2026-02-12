<x-layouts.app :title="__('app.add_mock_test')">
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.add_mock_test') }}</h1>
        </div>

        <form method="POST" action="{{ route('admin.mock-tests.store') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            @csrf
            @include('admin.mock-tests.partials.form')
            <button class="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white">{{ __('app.save') }}</button>
        </form>
    </div>
</x-layouts.app>
