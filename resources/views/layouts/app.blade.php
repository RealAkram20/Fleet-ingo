<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Fleet Log') — {{ config('app.name') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}?v={{ $brandVersion ?? '1' }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-asphalt-900 font-sans text-plate-50 antialiased">

<header class="border-b-[3px] border-ingo-500 bg-asphalt-800 bg-[repeating-linear-gradient(135deg,rgba(255,255,255,0.02)_0_2px,transparent_2px_14px)]">
    <div class="mx-auto max-w-6xl px-5 pb-0 pt-7">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <a href="{{ route('dashboard') }}" class="shrink-0">
                    <img src="{{ asset($branding['logo']) }}"
                         alt="{{ $branding['name'] }}"
                         class="h-11 w-auto">
                </a>
                <h1 class="m-0 font-display text-[30px] font-bold uppercase tracking-wide">Fleet Log</h1>
                @if ($branding['tagline'])
                    <p class="m-0 font-mono text-[12px] tracking-wide text-plate-300">{{ $branding['tagline'] }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <span class="mr-3 font-mono text-[11px] uppercase tracking-widest text-plate-300">
                    {{ auth()->user()->name }}
                    <span class="ml-1 rounded-sm border border-asphalt-600 px-1.5 py-0.5 text-[9px] text-plate-300/70">{{ auth()->user()->role }}</span>
                </span>
                <button type="submit"
                        class="cursor-pointer border-none bg-transparent font-mono text-[11px] uppercase tracking-widest text-plate-300 underline underline-offset-4 transition hover:text-ingo-500">
                    Sign out
                </button>
            </form>
        </div>

        <nav class="mt-5 flex w-fit gap-px overflow-hidden rounded-sm border border-asphalt-600 bg-asphalt-900">
            @php
                $tabs = [
                    ['dashboard', 'Dashboard', null],
                    ['readings.index', 'Log Reading', null],
                    ['riders.index', 'Riders', null],
                    ['bikes.index', 'Bikes', null],
                    ['users.index', 'Users', 'administer'],
                    ['settings.edit', 'Settings', 'administer'],
                ];
            @endphp
            @foreach ($tabs as [$route, $label, $ability])
                @continue($ability && ! auth()->user()->can($ability))
                @php $active = request()->routeIs(str_replace('.index', '.*', $route)) || request()->routeIs($route); @endphp
                <a href="{{ route($route) }}"
                   @class([
                       'px-4.5 py-2.5 font-display text-[13px] uppercase tracking-wide transition',
                       'bg-ingo-500 font-bold text-asphalt-900' => $active,
                       'font-medium text-plate-300 hover:bg-asphalt-700 hover:text-plate-50' => ! $active,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-5 pb-16 pt-7">
    <x-flash />
    @yield('content')
</main>

<footer class="mx-auto max-w-6xl px-5 pb-10 font-mono text-[11px] tracking-wide text-plate-300/60">
    {{ $branding['name'] }} · {{ now()->format('D d M Y, H:i') }} {{ config('app.timezone') }}
</footer>

</body>
</html>
