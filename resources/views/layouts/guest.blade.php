<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign in — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v={{ $brandVersion ?? '1' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-asphalt-900 font-sans text-plate-50 antialiased">

<div class="flex min-h-screen flex-col items-center justify-center px-5 py-10">

    <img src="{{ asset($branding['logo']) }}"
         alt="{{ $branding['name'] }}"
         class="h-16 w-auto">

    <p class="mb-8 mt-4 text-center font-mono text-[11px] uppercase tracking-widest text-plate-300">
        {{ $branding['name'] }}
    </p>

    <div class="w-full max-w-md overflow-hidden rounded-sm border border-asphalt-600 bg-asphalt-800">
        <div class="h-1 bg-ingo-500"></div>
        <div class="p-7">
            {{ $slot }}
        </div>
    </div>

    <p class="mt-7 text-center font-mono text-[11px] tracking-wide text-plate-300/60">
        Accounts are issued by the fleet administrator.
    </p>
</div>

</body>
</html>
