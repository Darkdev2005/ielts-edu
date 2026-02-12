<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'IELTS EDU') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=space+grotesk:400,500,600,700|fraunces:600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            .fade-up { animation: fadeUp .8s ease both; }
            .delay-1 { animation-delay: .1s; }
            .delay-2 { animation-delay: .2s; }
            .delay-3 { animation-delay: .3s; }
            .delay-4 { animation-delay: .4s; }
            .float-soft { animation: floatSoft 6s ease-in-out infinite; }
            .float-soft-2 { animation: floatSoft 7.5s ease-in-out infinite; }
            @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes floatSoft { 0%,100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 font-['Space_Grotesk']">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0" style="background: radial-gradient(circle at 10% 15%, rgba(59,130,246,0.18), transparent 45%), radial-gradient(circle at 85% 10%, rgba(56,189,248,0.18), transparent 45%), radial-gradient(circle at 50% 85%, rgba(14,116,144,0.12), transparent 45%);"></div>
            <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(90deg,rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(rgba(255,255,255,0.06)_1px,transparent_1px)] [background-size:40px_40px]"></div>

            <header class="relative z-10">
                <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white text-lg font-semibold ring-1 ring-slate-200">IE</div>
                        <div class="text-lg font-semibold tracking-wide">{{ config('app.name', 'IELTS EDU') }}</div>
                    </div>
                    <nav class="hidden items-center gap-6 text-sm text-slate-600 md:flex">
                        <a href="#features" class="hover:text-slate-900">Imkoniyatlar</a>
                        <a href="#ai" class="hover:text-slate-900">AI</a>
                        <a href="#flow" class="hover:text-slate-900">Jarayon</a>
                        <a href="#plans" class="hover:text-slate-900">Tariflar</a>
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="hover:text-slate-900">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900">Register</a>
                                @endif
                            @endauth
                        @endif
                    </nav>
                </div>
            </header>

            <main class="relative z-10">
                <section class="mx-auto grid max-w-6xl gap-10 px-6 pb-16 pt-10 lg:grid-cols-2 lg:items-center">
                    <div class="space-y-6">
                        <div class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-1 text-xs uppercase tracking-widest text-slate-600 fade-up">
                            IELTS EDU · A1–C1
                        </div>
                        <h1 class="text-4xl font-semibold leading-tight text-slate-900 sm:text-5xl lg:text-6xl fade-up delay-1">
                            IELTSni <span class="font-['Fraunces'] text-blue-600">AI bilan</span> tezroq, aniqroq o‘rganing.
                        </h1>
                        <p class="text-base text-slate-600 sm:text-lg fade-up delay-2">
                            Grammar, Reading, Listening, Writing va Speaking uchun yagona platforma.
                            Har bir qoidaga mos mashq, AI tushuntirishi, progress va aniq feedback.
                        </p>
                        <div class="flex flex-wrap gap-3 fade-up delay-3">
                            <a href="{{ Route::has('register') ? route('register') : route('login') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500">
                                Boshlash
                            </a>
                            <a href="{{ Route::has('login') ? route('login') : '#' }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:text-slate-900">
                                Demo ko‘rish
                            </a>
                            <a href="{{ route('grammar.index') }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900">
                                Grammar preview
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-4 text-xs text-slate-500 fade-up delay-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                AI bilan tushuntirish
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-cyan-500"></span>
                                Rule-centric mashqlar
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                                Progress & analytics
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="absolute -top-8 -left-6 h-28 w-28 rounded-full bg-blue-200/60 blur-2xl"></div>
                        <div class="absolute -bottom-10 right-10 h-32 w-32 rounded-full bg-blue-100/60 blur-2xl"></div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl backdrop-blur float-soft">
                            <div class="flex items-center justify-between">
                                <div class="text-xs uppercase tracking-widest text-slate-400">AI feedback</div>
                                <div class="rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-600">Live</div>
                            </div>
                            <div class="mt-4 text-lg font-semibold">“I am study English.”</div>
                            <div class="mt-2 text-sm text-slate-500">AI correction: <span class="text-slate-900">“I am studying English.”</span></div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="text-xs uppercase text-slate-400">Grammar rule</div>
                                    <div class="mt-2 text-sm text-slate-900">Present Continuous: am/is/are + V-ing</div>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="text-xs uppercase text-slate-400">Next step</div>
                                    <div class="mt-2 text-sm text-slate-900">10 ta mashq, 3 ta xato</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-lg float-soft-2">
                                <div class="text-xs uppercase text-slate-400">Reading</div>
                                <div class="mt-1 text-sm text-slate-700">A1–C1 matnlar + AI izoh</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-lg float-soft">
                                <div class="text-xs uppercase text-slate-400">Listening</div>
                                <div class="mt-1 text-sm text-slate-700">Audio + transkript + savollar</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="features" class="mx-auto max-w-6xl px-6 py-16">
                    <div class="flex flex-col gap-3">
                        <div class="text-xs uppercase tracking-widest text-slate-400">Nima uchun IELTS EDU</div>
                        <h2 class="text-3xl font-semibold">Platformani tark etolmaslik uchun sabablar</h2>
                        <p class="text-slate-500">Har bir modul real natijaga ishlaydi: qoidani tushunasiz, mashq qilasiz, AI izohlaydi.</p>
                    </div>
                    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">Rule‑centric Grammar</div>
                            <p class="mt-2 text-sm text-slate-500">Bitta qoida = bitta g‘oya. Mashqlar bevosita rule bilan bog‘langan.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">AI tushuntirishlar</div>
                            <p class="mt-2 text-sm text-slate-500">Nega xato bo‘lganini AI aniq izohlaydi va to‘g‘ri yo‘l ko‘rsatadi.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">Adaptive practice</div>
                            <p class="mt-2 text-sm text-slate-500">Zaif joylar bo‘yicha qayta mashq va shaxsiy tavsiyalar.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">Reading + Listening</div>
                            <p class="mt-2 text-sm text-slate-500">Level bo‘yicha kontent, audio, transkript, savollar.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">Mock testlar</div>
                            <p class="mt-2 text-sm text-slate-500">IELTS formatida real imtihon simulyatsiyasi.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">Progress & Analytics</div>
                            <p class="mt-2 text-sm text-slate-500">Har topic bo‘yicha foiz va tayyorgarlik darajasi.</p>
                        </div>
                    </div>
                </section>

                <section id="ai" class="mx-auto max-w-6xl px-6 py-16">
                    <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                        <div>
                            <div class="text-xs uppercase tracking-widest text-slate-400">AI bilan ishlash</div>
                            <h2 class="mt-3 text-3xl font-semibold">AI o‘qituvchi kabi yoningizda</h2>
                            <p class="mt-3 text-slate-500">
                                AI sizni faqat tekshirmaydi — u izohlaydi, misol beradi va keyingi mashqlarni moslaydi.
                            </p>
                            <div class="mt-6 space-y-3 text-sm text-slate-600">
                                <div>• AI Explanation: xatoni aniqlash va qoidaga bog‘lash</div>
                                <div>• Writing AI: Task 1/2 feedback, band score va tavsiyalar</div>
                                <div>• Speaking AI: prompt + texnika + strukturani baholash</div>
                            </div>
                        </div>
                        <div class="rounded-3xl border border-slate-200 bg-white p-6">
                            <div class="text-xs uppercase tracking-widest text-slate-400">AI Session</div>
                            <div class="mt-3 text-lg font-semibold">“I have went there.”</div>
                            <div class="mt-2 text-sm text-slate-500">AI: “I have gone there.” (Present Perfect)</div>
                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
                                Xato sabab: past participle noto‘g‘ri ishlatilgan. “Go → gone”.
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs text-blue-600">Rule link</span>
                                <span class="rounded-full bg-cyan-100 px-3 py-1 text-xs text-cyan-700">Practice set</span>
                                <span class="rounded-full bg-sky-100 px-3 py-1 text-xs text-[#e8c8b2]">Memory hint</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="flow" class="mx-auto max-w-6xl px-6 py-16">
                    <div class="text-xs uppercase tracking-widest text-slate-400">Jarayon</div>
                    <h2 class="mt-3 text-3xl font-semibold">Topic → Rule → Exercise → Feedback</h2>
                    <div class="mt-6 grid gap-4 lg:grid-cols-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-xs uppercase text-slate-400">1</div>
                            <div class="mt-2 text-sm font-semibold">Mavzuni tanlang</div>
                            <p class="mt-2 text-sm text-slate-500">A1–C1 bo‘yicha strukturali topiclar.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-xs uppercase text-slate-400">2</div>
                            <div class="mt-2 text-sm font-semibold">Qoidani tushuning</div>
                            <p class="mt-2 text-sm text-slate-500">Bitta qoida — bitta g‘oya, aniq misollar.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-xs uppercase text-slate-400">3</div>
                            <div class="mt-2 text-sm font-semibold">Mashq qiling</div>
                            <p class="mt-2 text-sm text-slate-500">MCQ, gap, TF, reorder — barchasi rule bilan bog‘langan.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-xs uppercase text-slate-400">4</div>
                            <div class="mt-2 text-sm font-semibold">AI feedback</div>
                            <p class="mt-2 text-sm text-slate-500">Xatoni tushunasiz va darhol tuzatasiz.</p>
                        </div>
                    </div>
                </section>

                <section id="plans" class="mx-auto max-w-6xl px-6 py-16">
                    <div class="text-xs uppercase tracking-widest text-slate-400">Tariflar</div>
                    <h2 class="mt-3 text-3xl font-semibold">Bepul boshlang, keyin kuchaytiring</h2>
                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">Free</div>
                            <p class="mt-2 text-sm text-slate-500">Grammar A1–A2, Reading/Listening preview, AI limit.</p>
                        </div>
                        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                            <div class="text-sm font-semibold">PLUS</div>
                            <p class="mt-2 text-sm text-slate-500">Full Grammar, Reading/Listening, AI feedback + explanations, Mock tests, Analytics.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="text-sm font-semibold">PRO</div>
                            <p class="mt-2 text-sm text-slate-500">Speaking AI, Advanced analytics, premium tools.</p>
                        </div>
                    </div>
                    <div class="mt-6">
                        <a href="{{ route('pricing') }}" class="inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                            Tariflarni ko‘rish
                        </a>
                    </div>
                </section>

                <section class="mx-auto max-w-6xl px-6 pb-20">
                    <div class="rounded-3xl border border-slate-200 bg-white p-8 text-center">
                        <h3 class="text-2xl font-semibold">IELTSni 90 kunda boshqacha his qiling</h3>
                        <p class="mt-2 text-slate-500">Bugun boshlang — har kuni 15–20 daqiqa bilan katta natija.</p>
                        <div class="mt-5 flex flex-wrap justify-center gap-3">
                            <a href="{{ Route::has('register') ? route('register') : route('login') }}" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500">
                                Ro‘yxatdan o‘tish
                            </a>
                            <a href="{{ Route::has('login') ? route('login') : '#' }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:text-slate-900">
                                Hisobim bor
                            </a>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="relative z-10 border-t border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl flex-col gap-4 px-6 py-6 text-xs text-slate-500 md:flex-row md:items-center md:justify-between">
                    <div>© {{ date('Y') }} {{ config('app.name', 'IELTS EDU') }}. All rights reserved.</div>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('pricing') }}" class="hover:text-slate-900">Pricing</a>
                        <a href="{{ route('login') }}" class="hover:text-slate-900">Login</a>
                        <a href="{{ route('register') }}" class="hover:text-slate-900">Register</a>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
