<x-guest-layout>
    <h2 class="m-0 mb-1 font-display text-[20px] uppercase tracking-wide">Sign in</h2>
    <p class="mb-6 mt-0 text-[13px] text-plate-300">Enter the details issued to you by the fleet administrator.</p>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-honeypot />

        <div>
            <x-input-label for="email" :value="__('Email')" class="mb-1.5" />
            <x-text-input id="email" type="email" name="email" :value="old('email')"
                          required autofocus autocomplete="username" placeholder="you@ingo.local" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" class="mb-1.5" />
            <x-password-input id="password" name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-[13px] text-plate-300">
            <input id="remember_me" type="checkbox" name="remember"
                   class="h-3.5 w-3.5 rounded-sm border-asphalt-600 bg-asphalt-900 text-ingo-500 focus:ring-ingo-500 focus:ring-offset-0">
            {{ __('Keep me signed in on this device') }}
        </label>

        <div class="flex items-center justify-between gap-4 pt-1">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="font-mono text-[11px] uppercase tracking-widest text-plate-300 underline underline-offset-4 transition hover:text-ingo-500">
                    {{ __('Forgot password?') }}
                </a>
            @endif

            <x-primary-button>{{ __('Sign in') }}</x-primary-button>
        </div>
    </form>
</x-guest-layout>
