@php
    $socialTelegram = config('app.social.telegram');
    $socialInstagram = config('app.social.instagram');
    $sponsorLabel = config('app.sponsors.contact_label');
    $sponsorUrl = config('app.sponsors.contact_url');
    $supportTelegram = config('app.support.telegram');
    $supportEmail = config('app.support.email');
@endphp

<footer class="mx-auto mt-6 max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
    <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-xs text-white/70 shadow-lg backdrop-blur">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-[10px] uppercase tracking-wide text-white/50">{{ __('app.community') }}</span>
                <div class="flex items-center gap-3 text-sm font-medium text-white/80">
                    @if($socialTelegram)
                        <a href="{{ $socialTelegram }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M21.9 4.6c.3-1.3-.9-2.4-2.1-2L2.7 9.1c-1.5.6-1.4 2.8.2 3.2l4.7 1.4 1.8 5.2c.5 1.4 2.4 1.7 3.2.5l2.7-3.7 4.7 3.4c1.3 1 3.2.3 3.5-1.3l2.8-13.2zM9.6 12.8l8.4-5.1c.4-.2.8.3.5.6l-7.1 6.7-.3 3.2-1.6-4.6-3.2-1.1c-.5-.2-.5-.9 0-1l12.3-4.7-9 7.5z"/>
                            </svg>
                            <span>Telegram</span>
                        </a>
                    @endif
                    @if($socialInstagram)
                        <a href="{{ $socialInstagram }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M7.5 3C4.5 3 2 5.5 2 8.5v7C2 18.5 4.5 21 7.5 21h9c3 0 5.5-2.5 5.5-5.5v-7C22 5.5 19.5 3 16.5 3h-9zm0 2h9C18.4 5 20 6.6 20 8.5v7c0 1.9-1.6 3.5-3.5 3.5h-9C5.6 19 4 17.4 4 15.5v-7C4 6.6 5.6 5 7.5 5zm9.2 1.5a1.3 1.3 0 1 0 0 2.6 1.3 1.3 0 0 0 0-2.6zM12 7.4A4.6 4.6 0 1 0 12 16.6 4.6 4.6 0 0 0 12 7.4zm0 2a2.6 2.6 0 1 1 0 5.2 2.6 2.6 0 0 1 0-5.2z"/>
                            </svg>
                            <span>Instagram</span>
                        </a>
                    @endif
                    @if(!$socialTelegram && !$socialInstagram)
                        <span class="text-white/40">{{ __('app.not_set') }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="rounded-full border border-white/15 px-3 py-1 text-xs font-semibold text-white/80 hover:text-white hover:border-white/40"
                    @click="$dispatch('open-support')"
                >
                    {{ __('app.support_open') }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <span class="text-[10px] uppercase tracking-wide text-white/50">{{ __('app.sponsors') }}</span>
                <div class="text-sm text-white/70">
                    @if($sponsorUrl && $sponsorLabel)
                        <a href="{{ $sponsorUrl }}" target="_blank" rel="noopener" class="font-semibold text-white/80 hover:text-white">
                            {{ $sponsorLabel }}
                        </a>
                    @else
                        {{ __('app.sponsors_none') }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</footer>
