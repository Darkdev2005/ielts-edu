<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold">{{ __('auth.register_title') }}</h2>
        <p class="text-sm text-slate-500">{{ __('auth.register_subtitle') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('auth.name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('auth.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('auth.password')" />
            <div class="relative mt-1 flex items-center rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500" x-data="{ show: false }">
                <x-text-input id="password"
                              class="w-full border-0 bg-transparent px-3 py-2 pr-12 shadow-none focus:ring-0"
                              type="password"
                              x-bind:type="show ? 'text' : 'password'"
                              name="password"
                              required autocomplete="new-password" />
                <button type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 transition hover:text-slate-700"
                        x-on:click="show = !show"
                        x-bind:aria-pressed="show.toString()"
                        aria-label="Toggle password visibility">
                    <svg x-show="!show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    </svg>
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 10.5a2 2 0 0 0 2.828 2.828" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 4.24A9.958 9.958 0 0 1 12 4c4.477 0 8.268 2.943 9.542 7a9.972 9.972 0 0 1-4.052 5.369" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.228 6.228A9.972 9.972 0 0 0 2.458 12c1.274 4.057 5.065 7 9.542 7a9.96 9.96 0 0 0 4.28-.96" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('auth.confirm_password')" />
            <div class="relative mt-1 flex items-center rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500" x-data="{ show: false }">
                <x-text-input id="password_confirmation"
                              class="w-full border-0 bg-transparent px-3 py-2 pr-12 shadow-none focus:ring-0"
                              type="password"
                              x-bind:type="show ? 'text' : 'password'"
                              name="password_confirmation" required autocomplete="new-password" />
                <button type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 transition hover:text-slate-700"
                        x-on:click="show = !show"
                        x-bind:aria-pressed="show.toString()"
                        aria-label="Toggle password visibility">
                    <svg x-show="!show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    </svg>
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 10.5a2 2 0 0 0 2.828 2.828" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 4.24A9.958 9.958 0 0 1 12 4c4.477 0 8.268 2.943 9.542 7a9.972 9.972 0 0 1-4.052 5.369" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.228 6.228A9.972 9.972 0 0 0 2.458 12c1.274 4.057 5.065 7 9.542 7a9.96 9.96 0 0 0 4.28-.96" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('auth.already_registered') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('auth.register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
