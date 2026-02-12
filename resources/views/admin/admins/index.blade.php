<x-layouts.app :title="__('app.admin_management')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                {{ __('app.super_admin') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.admin_management') }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ __('app.admin_management_intro') }}</p>
        </div>
        <form method="GET" action="{{ route('admin.admins.index') }}" class="flex flex-col gap-2 md:flex-row md:items-center">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="{{ __('app.search_placeholder') }}"
                class="w-full rounded-xl border border-slate-200 bg-white/90 px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-slate-300 focus:outline-none md:w-64"
            />
            <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-lg" type="submit">
                {{ __('app.search') }}
            </button>
        </form>
    </div>

    @if($errors->any())
        <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mt-8 space-y-4">
        @foreach($users as $user)
            @php
                $roleLabel = $user->is_super_admin
                    ? __('app.super_admin')
                    : ($user->is_admin ? __('app.admin') : __('app.user'));
                $badgeClass = $user->is_super_admin
                    ? 'bg-amber-100 text-amber-700'
                    : ($user->is_admin ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600');
            @endphp
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-semibold text-slate-900">{{ $user->name }}</div>
                    <div class="text-xs text-slate-500">{{ $user->email }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm text-slate-600">
                    <div>
                        <div class="text-xs text-slate-400">{{ __('app.role') }}</div>
                        <div class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $badgeClass }}">
                            {{ $roleLabel }}
                        </div>
                    </div>
                    @if($user->is_super_admin)
                        <div class="text-xs text-slate-400">{{ __('app.super_admin_locked') }}</div>
                    @else
                        <form method="POST" action="{{ route('admin.admins.toggle', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="rounded-xl px-4 py-2 text-xs font-semibold uppercase tracking-wide shadow-sm transition
                                {{ $user->is_admin ? 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}"
                            >
                                {{ $user->is_admin ? __('app.remove_admin') : __('app.make_admin') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">{{ $users->links() }}</div>
</x-layouts.app>
