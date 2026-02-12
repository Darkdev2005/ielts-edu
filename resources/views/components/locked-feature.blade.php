@props([
    'label',
])

<button type="button"
    class="inline-flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100"
    @click="$dispatch('open-paywall', { feature: @json($label) })"
>
    <span class="inline-flex h-4 w-4 items-center justify-center text-rose-600">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="4" y="9" width="12" height="8" rx="2" />
            <path d="M7 9V7a3 3 0 0 1 6 0v2" stroke-linecap="round" />
        </svg>
    </span>
    <span>{{ $label }}</span>
</button>
