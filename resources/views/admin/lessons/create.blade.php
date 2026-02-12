<x-layouts.app :title="__('app.create_lesson')">
    <div class="flex flex-col gap-2">
        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
            {{ __('app.admin') }}
        </div>
        <h1 class="text-3xl font-semibold">{{ __('app.create_lesson') }}</h1>
    </div>

    <form method="POST" action="{{ route('admin.lessons.store') }}" class="mt-8 space-y-4 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        @csrf
        @include('admin.lessons.partials.form', ['lesson' => null])
        <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg">{{ __('app.save') }}</button>
    </form>
</x-layouts.app>
