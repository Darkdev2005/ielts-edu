@php
    $supportTelegram = trim((string) config('app.support.telegram'));
    $supportEmail = trim((string) config('app.support.email'));
    $supportTelegramUrl = $supportTelegram ?: null;
    if ($supportTelegram && !str_starts_with($supportTelegram, 'http')) {
        $supportTelegramUrl = 'https://t.me/' . ltrim($supportTelegram, '@');
    }
    $subject = __('app.support_email_subject', ['app' => config('app.name', 'IELTS EDU')]);
    $supportEmailUrl = $supportEmail ? ('mailto:' . $supportEmail . '?subject=' . rawurlencode($subject)) : null;
    $supportEmailWebUrl = $supportEmail ? ('https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode($supportEmail) . '&su=' . rawurlencode($subject)) : null;
@endphp

<div
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 px-4"
    x-show="supportOpen"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    @click.self="supportOpen = false"
>
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 text-slate-900 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold">{{ __('app.support_title') }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ __('app.support_hint') }}</p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700" @click="supportOpen = false">x</button>
        </div>

        <div class="mt-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">{{ __('app.support_choose_type') }}</div>
            <div class="mt-3 flex flex-wrap gap-2">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('app.support_complaint') }}</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('app.support_suggestion') }}</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('app.support_question') }}</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('app.support_feedback') }}</span>
            </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            @if($supportTelegramUrl)
                <a href="{{ $supportTelegramUrl }}" target="_blank" rel="noopener" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                    {{ __('app.support_contact_telegram') }}
                </a>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    {{ __('app.not_set') }}
                </div>
            @endif
            @if($supportEmailWebUrl)
                <a href="{{ $supportEmailWebUrl }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('app.support_contact_email') }}
                </a>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    {{ __('app.not_set') }}
                </div>
            @endif
        </div>

        <p class="mt-4 text-xs text-slate-500">{{ __('app.support_contact_hint') }}</p>
    </div>
</div>
