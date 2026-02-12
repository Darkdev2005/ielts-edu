<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold">{{ __('auth.forgot_password_title') }}</h2>
        <p class="text-sm text-slate-500">{{ __('auth.forgot_password_help') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('auth.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('auth.send_password_reset_link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
