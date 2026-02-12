<x-layouts.app :title="__('app.speaking')">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-purple-700">
                {{ __('app.speaking') }}
            </div>
            <h1 class="mt-4 text-3xl font-semibold">{{ __('app.speaking_title') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('app.speaking_intro') }}</p>
        </div>
    </div>

    @if(empty($canSpeaking))
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-sm">
            <div class="font-semibold">{{ __('app.speaking_pro_hint') }}</div>
            <div class="mt-1 text-xs text-amber-800">{{ __('app.speaking_preview_intro') }}</div>
        </div>
    @endif

    @if(session('status'))
        <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 shadow-sm">
            {{ session('status') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 shadow-sm">
            <div class="font-semibold">Xatolik:</div>
            <ul class="mt-2 list-disc pl-5 text-xs text-rose-800">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-8 grid gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-400">Part 1</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">Short questions</div>
            <p class="mt-2 text-sm text-slate-600">1–2 sentences. Daily topics.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-400">Part 2</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">Cue card</div>
            <p class="mt-2 text-sm text-slate-600">1–2 minutes. Describe & expand.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/95 p-5 shadow-sm">
            <div class="text-xs font-semibold uppercase text-slate-400">Part 3</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">Follow‑up</div>
            <p class="mt-2 text-sm text-slate-600">Explain ideas with reasons.</p>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <div class="text-sm font-semibold text-slate-600">{{ __('app.speaking_preview_intro') }}</div>
        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach([1,2,3] as $part)
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                    <div class="text-xs font-semibold uppercase text-slate-400">Part {{ $part }}</div>
                    <div class="mt-2 space-y-2 text-sm text-slate-700">
                        @foreach(($promptsByPart[$part] ?? collect()) as $item)
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <div class="text-sm text-slate-800">
                                    {{ is_object($item) ? $item->prompt : $item }}
                                    @if(is_object($item) && $item->difficulty)
                                        <span class="ml-2 text-[10px] font-semibold uppercase text-slate-400">{{ $item->difficulty }}</span>
                                    @endif
                                </div>
                                @if(is_object($item))
                                    <form method="POST" action="{{ route('speaking.submissions.store', $item) }}" class="mt-3 space-y-2" enctype="multipart/form-data" data-speaking-form>
                                        @csrf
                                        <textarea
                                            name="response_text"
                                            rows="3"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                            placeholder="Write your answer..."
                                        ></textarea>
                                        <input type="file" name="audio" accept="audio/*" class="hidden" data-audio-input>
                                        <div class="flex items-center gap-2 text-xs text-slate-500">
                                            <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700" data-record-start>Record</button>
                                            <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700" data-record-stop disabled>Stop</button>
                                            <span data-record-status>Audio: off</span>
                                        </div>
                                        <audio class="hidden w-full" controls data-record-preview></audio>
                                        <div class="flex items-center justify-between text-xs text-slate-400">
                                            <span>Part {{ $item->part }}</span>
                                            <button class="rounded-lg bg-slate-900 px-3 py-1 text-xs font-semibold text-white">
                                                Submit
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @if(!empty($recentSubmissions) && $recentSubmissions->count())
            <div class="mt-6 border-t border-slate-100 pt-4">
                <div class="text-xs font-semibold uppercase text-slate-400">Recent submissions</div>
                <div class="mt-3 space-y-2 text-sm text-slate-700">
                    @foreach($recentSubmissions as $submission)
                            <div class="rounded-lg border border-slate-100 bg-white px-3 py-2">
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span>{{ $submission->created_at->format('Y-m-d H:i') }}</span>
                                <a class="font-semibold text-slate-600 hover:text-slate-900" href="{{ route('speaking.submissions.show', $submission) }}">View</a>
                            </div>
                            <div class="mt-1 text-sm text-slate-700">{{ \Illuminate\Support\Str::limit($submission->response_text, 180) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <script>
        (() => {
            const forms = document.querySelectorAll('[data-speaking-form]');
            if (!forms.length) return;

            const setupRecorder = async (form) => {
                const startBtn = form.querySelector('[data-record-start]');
                const stopBtn = form.querySelector('[data-record-stop]');
                const statusEl = form.querySelector('[data-record-status]');
                const audioInput = form.querySelector('[data-audio-input]');
                const preview = form.querySelector('[data-record-preview]');
                if (!startBtn || !stopBtn || !statusEl || !audioInput) return;

                let mediaRecorder = null;
                let chunks = [];

                const stopRecording = () => {
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                    }
                };

                startBtn.addEventListener('click', async () => {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        mediaRecorder = new MediaRecorder(stream);
                        chunks = [];
                        mediaRecorder.ondataavailable = (e) => {
                            if (e.data.size > 0) chunks.push(e.data);
                        };
                        mediaRecorder.onstop = () => {
                            const blob = new Blob(chunks, { type: 'audio/webm' });
                            const file = new File([blob], `recording-${Date.now()}.webm`, { type: blob.type });
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            audioInput.files = dt.files;
                            if (preview) {
                                preview.src = URL.createObjectURL(blob);
                                preview.classList.remove('hidden');
                            }
                            statusEl.textContent = 'Audio: ready';
                            stopBtn.disabled = true;
                            startBtn.disabled = false;
                            stream.getTracks().forEach((t) => t.stop());
                        };
                        mediaRecorder.start();
                        statusEl.textContent = 'Recording...';
                        startBtn.disabled = true;
                        stopBtn.disabled = false;
                    } catch (e) {
                        statusEl.textContent = 'Mic blocked';
                    }
                });

                stopBtn.addEventListener('click', () => {
                    stopRecording();
                });
            };

            forms.forEach((form) => {
                setupRecorder(form);
            });
        })();
    </script>
</x-layouts.app>
