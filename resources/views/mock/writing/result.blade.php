<x-layouts.app :title="__('app.result').' · Writing Mock'">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                {{ __('app.mock_tests') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">Writing Mock · {{ __('app.result') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ $submission->task?->title }}</p>
        </div>
        <div class="rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white">
            <div class="text-white/70">{{ __('app.writing_band_score') }}</div>
            <div class="text-2xl font-semibold" data-band-score>
                {{ is_numeric($submission->band_score) ? number_format((float) $submission->band_score, 1) : '-' }}
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ session('status') }}</div>
    @endif

    <div class="mt-6 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm"
        data-mock-writing-config
        data-status-url="{{ route('mock.writing.status', $submission) }}"
    >
        <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_status') }}</div>
            <div class="text-xs text-slate-400">{{ $submission->submitted_at?->format('Y-m-d H:i') }}</div>
        </div>
        <div class="mt-3 text-sm text-slate-700" data-status-text>{{ $submission->status }}</div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_your_response') }}</div>
            <div class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $submission->response_text }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
            <div class="text-sm font-semibold text-slate-600">{{ __('app.writing_ai_feedback') }}</div>
            <div class="mt-3 text-sm text-slate-700" data-feedback-box>
                {{ $submission->ai_feedback ?: __('app.writing_feedback_pending') }}
            </div>
        </div>
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('mock.writing.show', $submission->task) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Retry
        </a>
        <a href="{{ route('mock.writing.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            {{ __('app.back_to_list') }}
        </a>
    </div>

    <script>
        (() => {
            const config = document.querySelector('[data-mock-writing-config]');
            if (!config) return;

            const statusEl = config.querySelector('[data-status-text]');
            const feedbackEl = document.querySelector('[data-feedback-box]');
            const bandEl = document.querySelector('[data-band-score]');
            const url = config.dataset.statusUrl;

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const renderFeedback = (payload, fallback) => {
                if (payload && typeof payload === 'object') {
                    const summary = payload.summary ? `<div class="font-semibold">${escapeHtml(payload.summary)}</div>` : '';
                    const strengths = Array.isArray(payload.strengths) && payload.strengths.length
                        ? `<ul class="mt-2 list-disc pl-5">${payload.strengths.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
                        : '';
                    const weaknesses = Array.isArray(payload.weaknesses) && payload.weaknesses.length
                        ? `<ul class="mt-2 list-disc pl-5">${payload.weaknesses.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
                        : '';
                    feedbackEl.innerHTML = `${summary}${strengths}${weaknesses}` || escapeHtml(fallback || '');
                    return;
                }

                feedbackEl.textContent = fallback || '';
            };

            const tick = async () => {
                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (statusEl) statusEl.textContent = data.status || '';
                    if (bandEl) {
                        bandEl.textContent = typeof data.band_score === 'number'
                            ? Number(data.band_score).toFixed(1)
                            : '-';
                    }
                    renderFeedback(data.ai_feedback_json, data.ai_feedback || '');
                    if (data.status === 'done' || data.status === 'failed') {
                        clearInterval(loop);
                    }
                } catch (e) {
                    // ignore polling error
                }
            };

            const loop = setInterval(tick, 4000);
            tick();
        })();
    </script>
</x-layouts.app>
