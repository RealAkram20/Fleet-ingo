@extends('layouts.app')
@section('title', 'Settings')

@section('content')

<h2 class="m-0 font-display text-xl uppercase tracking-wide">Settings</h2>
<p class="mb-5 mt-1 text-[13px] text-plate-300">
    Application-wide configuration. Changes take effect immediately for everyone.
</p>

{{-- ------------------------------------------------------------- branding --}}
<x-panel title="Branding" subtitle="The name and mark shown across the app and on the sign-in screen.">
    <form method="POST" action="{{ route('settings.branding') }}" enctype="multipart/form-data">
        @csrf @method('PATCH')

        <div class="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
            <x-field label="Application name" name="app_name" required :value="$settings['app_name']" />
            <x-field label="Tagline" name="tagline" :value="$settings['tagline']"
                     hint="Shown beside the logo in the header." />
        </div>

        <div class="mt-5 grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-5">
            <div>
                <p class="mb-2 font-mono text-[10px] uppercase tracking-widest text-plate-300">Current logo</p>
                <div class="mb-3 rounded-sm border border-asphalt-600 bg-asphalt-900 p-4">
                    <img src="{{ asset($settings['logo_path']) }}" alt="Current logo" class="h-12 w-auto">
                </div>
                <x-field label="Replace logo" name="logo" type="file" accept="image/*"
                         hint="PNG with a transparent background works best. Max 2 MB." />
            </div>

            <div>
                <p class="mb-2 font-mono text-[10px] uppercase tracking-widest text-plate-300">Current icon</p>
                <div class="mb-3 rounded-sm border border-asphalt-600 bg-asphalt-900 p-4">
                    <img src="{{ asset($settings['icon_path']) }}" alt="Current icon" class="h-12 w-12 object-contain">
                </div>
                <x-field label="Replace icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp"
                         hint="Square. The browser tab icons are rebuilt from it." />
            </div>
        </div>

        <div class="mt-4">
            <x-btn variant="primary">Save Branding</x-btn>
        </div>
    </form>
</x-panel>

{{-- ---------------------------------------------------------------- fleet --}}
<x-panel title="Fleet defaults and thresholds"
         subtitle="What counts as due soon, and what a new bike starts with.">
    <form method="POST" action="{{ route('settings.fleet') }}">
        @csrf @method('PATCH')

        <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">
            <x-field label="Default service interval (km)" name="default_service_interval_km" type="number" required
                     :value="$settings['default_service_interval_km']"
                     hint="Pre-filled when adding a bike." />

            <x-field label="Default service interval (months)" name="default_service_interval_months" type="number"
                     :value="$settings['default_service_interval_months']"
                     hint="Blank means new bikes track distance only." />

            <x-field label="Flag service at (km remaining)" name="due_soon_km" type="number" required
                     :value="$settings['due_soon_km']"
                     hint="Below this a bike shows Due Soon." />

            <x-field label="Flag service at (days remaining)" name="due_soon_days" type="number" required
                     :value="$settings['due_soon_days']" />

            <x-field label="Flag licence at (days remaining)" name="licence_warn_days" type="number" required
                     :value="$settings['licence_warn_days']" />

            <x-select-field label="Timezone" name="timezone" required
                            hint="Dates on readings and services follow this.">
                @foreach ($timezones as $tz)
                    <option value="{{ $tz }}" @selected($settings['timezone'] === $tz)>{{ $tz }}</option>
                @endforeach
            </x-select-field>
        </div>

        <div class="mt-4">
            <x-btn variant="primary">Save Fleet Defaults</x-btn>
        </div>
    </form>
</x-panel>

{{-- ----------------------------------------------------------------- mail --}}
<x-panel title="Outgoing email (SMTP)"
         subtitle="Used for password resets and any notifications.">
    <div class="mb-4 rounded-sm border px-4 py-3 text-[13px]
        {{ $mailConfigured ? 'border-status-ok/40 bg-status-ok-dim text-status-ok' : 'border-status-warn/40 bg-status-warn-dim text-status-warn' }}">
        @if ($mailConfigured)
            SMTP is configured. Mail is sent through {{ $settings['mail_host'] }}.
        @else
            No SMTP host set. Mail is written to <span class="font-mono">storage/logs/laravel.log</span> instead of being sent.
        @endif
    </div>

    <form method="POST" action="{{ route('settings.mail') }}">
        @csrf @method('PATCH')

        <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">
            <x-field label="SMTP host" name="mail_host" :value="$settings['mail_host']"
                     placeholder="e.g. smtp.gmail.com" hint="Leave blank to keep mail in the log." />
            <x-field label="Port" name="mail_port" type="number" :value="$settings['mail_port']" placeholder="587" />

            <x-select-field label="Encryption" name="mail_encryption">
                @foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $value => $label)
                    <option value="{{ $value }}" @selected(($settings['mail_encryption'] ?: 'none') === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>

            <x-field label="Username" name="mail_username" :value="$settings['mail_username']" autocomplete="off" />

            <x-field label="Password" name="mail_password" type="password" autocomplete="new-password"
                     :hint="$settings['mail_password'] ? 'A password is stored. Blank leaves it unchanged.' : 'Stored encrypted.'" />

            <x-field label="From address" name="mail_from_address" type="email" required :value="$settings['mail_from_address']" />
            <x-field label="From name" name="mail_from_name" required :value="$settings['mail_from_name']" />
        </div>

        <div class="mt-4">
            <x-btn variant="primary">Save Email Settings</x-btn>
        </div>
    </form>

    <div class="mt-5 border-t border-asphalt-600 pt-5">
        <form method="POST" action="{{ route('settings.mail.test') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-[240px] flex-1">
                <x-field label="Send a test message to" name="test_email" type="email"
                         :value="auth()->user()->email" />
            </div>
            <x-btn variant="ghost">Send Test</x-btn>
        </form>
    </div>
</x-panel>

@endsection
