<x-layouts.app :title="__('app.grammar')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                {{ __('app.grammar') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.grammar_topics') }}</h1>
            <p class="text-sm text-slate-500">{{ __('app.grammar_intro') }}</p>
        </div>
    </div>

    <style>
        @media (min-width: 1024px) {
            .grammar-grid {
                grid-template-columns: 280px minmax(0, 1fr);
            }
            .grammar-grid.is-collapsed {
                grid-template-columns: 0 minmax(0, 1fr);
            }
            .grammar-panel {
                width: 280px;
            }
        }
        @media (min-width: 1280px) {
            .grammar-grid {
                grid-template-columns: 320px minmax(0, 1fr);
            }
            .grammar-panel {
                width: 320px;
            }
        }
    </style>

    <div x-data="{ topicPanelOpen: true }">
        <div class="mt-6 lg:hidden">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ __('app.grammar_topics') }}</div>
                <details class="mt-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <summary class="cursor-pointer list-none text-sm font-semibold text-slate-700">
                        {{ __('app.select_topic') }}
                    </summary>
                    <div class="mt-3 space-y-2">
                        @forelse($topics as $topic)
                            @php
                                $isRecommended = isset($recommendedTopicId) && $recommendedTopicId === $topic->id;
                            $progress = $topicProgress[$topic->id] ?? null;
                            $hasProgress = $progress !== null;
                            $progressPercent = $hasProgress ? (int) ($progress['percent'] ?? 0) : 0;
                            $progressClass = $hasProgress
                                ? ($progressPercent >= 80 ? 'bg-emerald-500' : ($progressPercent >= 50 ? 'bg-amber-500' : 'bg-rose-500'))
                                : 'bg-slate-300';
                        @endphp
                            <a href="{{ route('grammar.show', $topic) }}" class="rounded-xl border border-slate-100 px-3 py-2 text-sm text-slate-700 transition hover:border-slate-200 hover:bg-slate-50 {{ $isRecommended ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : '' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate">{{ $topic->title }}</span>
                                    <span class="ml-2 flex items-center gap-1">
                                        @if($topic->cefr_level)
                                            <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                                {{ $topic->cefr_level }}
                                            </span>
                                        @endif
                                        @if($isRecommended)
                                            <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">
                                                {{ __('app.recommended') }}
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-slate-200">
                                    <div class="h-full rounded-full {{ $progressClass }}" style="width: {{ $progressPercent }}%"></div>
                                </div>
                                <span class="text-[10px] font-semibold text-slate-500">{{ $progressPercent }}%</span>
                            </div>
                            </a>
                        @empty
                            <div class="text-sm text-slate-500">{{ __('app.no_grammar_topics') }}</div>
                        @endforelse
                    </div>
                </details>
            </div>
        </div>

        <div class="mt-6 hidden lg:flex items-center justify-end">
            <button type="button"
                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700"
                @click="topicPanelOpen = !topicPanelOpen"
            >
                <svg class="h-4 w-4 transition" :class="topicPanelOpen ? 'rotate-0 text-emerald-600' : 'rotate-180 text-slate-500'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3.293 10.707a1 1 0 0 1 0-1.414l4.5-4.5a1 1 0 1 1 1.414 1.414L5.914 9H16a1 1 0 1 1 0 2H5.914l3.293 3.293a1 1 0 1 1-1.414 1.414l-4.5-4.5z" clip-rule="evenodd" />
                </svg>
                <span>{{ __('app.grammar_topics') }}</span>
            </button>
        </div>

        <div class="mt-8 grid gap-6 grammar-grid transition-[grid-template-columns] duration-300"
            :class="topicPanelOpen ? '' : 'is-collapsed'">
            <div class="hidden lg:block overflow-hidden transition-all duration-300 grammar-panel"
                :class="topicPanelOpen ? 'opacity-100 translate-x-0' : '-translate-x-6 opacity-0 pointer-events-none'">
            <div class="sticky top-24">
                    <div class="w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('app.grammar_topics') }}</div>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                {{ $topics->count() }}
                            </span>
                        </div>
                        <div class="mt-3 max-h-[70vh] space-y-2 overflow-auto pr-1">
                            @forelse($topics as $topic)
                                @php
                                    $isRecommended = isset($recommendedTopicId) && $recommendedTopicId === $topic->id;
                                    $progress = $topicProgress[$topic->id] ?? null;
                                    $hasProgress = $progress !== null;
                                    $progressPercent = $hasProgress ? (int) ($progress['percent'] ?? 0) : 0;
                                    $progressClass = $hasProgress
                                        ? ($progressPercent >= 80 ? 'bg-emerald-500' : ($progressPercent >= 50 ? 'bg-amber-500' : 'bg-rose-500'))
                                        : 'bg-slate-300';
                                @endphp
                                <a href="{{ route('grammar.show', $topic) }}" class="group rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-700 transition hover:border-emerald-200 hover:bg-emerald-50/60 {{ $isRecommended ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : '' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate font-semibold text-slate-800 group-hover:text-emerald-700">{{ $topic->title }}</span>
                                        <div class="flex items-center gap-1">
                                            @if($topic->cefr_level)
                                                <span class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                                    {{ $topic->cefr_level }}
                                                </span>
                                            @endif
                                            @if($isRecommended)
                                                <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">
                                                    {{ __('app.recommended') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="h-1.5 flex-1 rounded-full bg-slate-200">
                                            <div class="h-full rounded-full {{ $progressClass }}" style="width: {{ $progressPercent }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-semibold text-slate-500">{{ $progressPercent }}%</span>
                                    </div>
                                </a>
                            @empty
                                <div class="text-sm text-slate-500">{{ __('app.no_grammar_topics') }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($topics as $topic)
                    @php
                        $isRecommended = isset($recommendedTopicId) && $recommendedTopicId === $topic->id;
                        $progress = $topicProgress[$topic->id] ?? null;
                        $hasProgress = $progress !== null;
                        $progressPercent = $hasProgress ? (int) ($progress['percent'] ?? 0) : 0;
                        $progressClass = $hasProgress
                            ? ($progressPercent >= 80 ? 'bg-emerald-500' : ($progressPercent >= 50 ? 'bg-amber-500' : 'bg-rose-500'))
                            : 'bg-slate-300';
                    @endphp
                    <a href="{{ route('grammar.show', $topic) }}" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md {{ $isRecommended ? 'ring-2 ring-emerald-400 bg-emerald-50/60' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ $topic->title }}</div>
                                @if($topic->description)
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ \Illuminate\Support\Str::limit($topic->description, 90) }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                @if($topic->cefr_level)
                                    <div class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                        {{ $topic->cefr_level }}
                                    </div>
                                @endif
                                @if($isRecommended)
                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">{{ __('app.recommended') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 text-[11px] text-slate-500">
                            {{ __('app.rules') }}: {{ $topic->rules_count }} - {{ __('app.exercises') }}: {{ $topic->exercises_count }}
                        </div>
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-[10px] font-semibold text-slate-500">
                                <span>{{ __('app.progress') }}</span>
                                <span>{{ $progressPercent }}%</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-slate-200">
                                <div class="h-full rounded-full {{ $progressClass }}" style="width: {{ $progressPercent }}%"></div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                        {{ __('app.no_grammar_topics') }}
                    </div>
                @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
