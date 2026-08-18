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
<body class="min-h-screen overflow-x-hidden bg-asphalt-900 font-sans text-plate-50 antialiased">

<header class="border-b-[3px] border-ingo-500 bg-asphalt-800 bg-[repeating-linear-gradient(135deg,rgba(255,255,255,0.02)_0_2px,transparent_2px_14px)]">
    <div class="mx-auto max-w-6xl px-4 pb-0 pt-4 sm:px-5 sm:pt-7">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
            <div class="flex min-w-0 items-center gap-x-3 sm:gap-x-4">
                <a href="{{ route('dashboard') }}" class="shrink-0">
                    <img src="{{ asset($branding['logo']) }}"
                         alt="{{ $branding['name'] }}"
                         class="h-9 w-auto sm:h-11">
                </a>
                <h1 class="m-0 font-display text-[22px] font-bold uppercase leading-none tracking-wide sm:text-[30px]">Fleet Log</h1>
                @if ($branding['tagline'])
                    {{-- The tagline is decoration; on a phone it costs a whole line. --}}
                    <p class="m-0 hidden font-mono text-[12px] tracking-wide text-plate-300 lg:block">{{ $branding['tagline'] }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('logout') }}" class="flex shrink-0 items-center gap-3">
                @csrf
                <span class="max-w-[45vw] truncate font-mono text-[11px] uppercase tracking-widest text-plate-300 sm:max-w-none">
                    {{ auth()->user()->name }}
                    <span class="ml-1 rounded-sm border border-asphalt-600 px-1.5 py-0.5 text-[9px] text-plate-300/70">{{ auth()->user()->role }}</span>
                </span>
                <button type="submit"
                        class="min-h-11 cursor-pointer touch-manipulation border-none bg-transparent font-mono text-[11px] uppercase tracking-widest text-plate-300 underline underline-offset-4 transition hover:text-ingo-500">
                    Sign out
                </button>
            </form>
        </div>

        {{--
            The tab strip. On a phone it is a horizontal scroller with the
            scrollbar hidden; app.js scrolls the active tab into view.
        --}}
        <nav data-tabs class="scroll-strip mt-4 flex w-full gap-px overflow-x-auto rounded-sm border border-asphalt-600 bg-asphalt-900 sm:mt-5 sm:w-fit sm:overflow-hidden">
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
                   @if ($active) aria-current="page" @endif
                   @class([
                       'shrink-0 touch-manipulation whitespace-nowrap px-4 py-3 font-display text-[13px] uppercase tracking-wide transition sm:px-4.5 sm:py-2.5',
                       'bg-ingo-500 font-bold text-asphalt-900' => $active,
                       'font-medium text-plate-300 hover:bg-asphalt-700 hover:text-plate-50' => ! $active,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 pb-16 pt-5 sm:px-5 sm:pt-7">
    <x-flash />
    @yield('content')
</main>

<footer class="mx-auto max-w-6xl px-4 pb-10 font-mono text-[11px] tracking-wide text-plate-300/60 sm:px-5">
    {{ $branding['name'] }} · {{ now()->format('D d M Y, H:i') }} {{ config('app.timezone') }}
</footer>

</body>
</html>
