<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-semibold">{{ __('auth.verify_email_title') }}</h2>
        <p class="text-sm text-slate-500">{{ __('auth.verify_email_help') }}</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('auth.verification_link_sent') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('auth.resend_verification_email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('app.logout') }}
            </button>
        </form>
    </div>
</x-guest-layout>
