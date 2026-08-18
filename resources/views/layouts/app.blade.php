<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Fleet Log') — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-asphalt-900 font-sans text-plate-50 antialiased">

<header class="border-b-[3px] border-ingo-500 bg-asphalt-800 bg-[repeating-linear-gradient(135deg,rgba(255,255,255,0.02)_0_2px,transparent_2px_14px)]">
    <div class="mx-auto max-w-6xl px-5 pb-0 pt-7">
        <div class="flex flex-wrap items-baseline justify-between gap-4">
            <div class="flex flex-wrap items-baseline gap-3.5">
                <h1 class="m-0 font-display text-[34px] font-bold uppercase tracking-wide">
                    IN<span class="text-ingo-500">GO</span> FLEET LOG
                </h1>
                <p class="m-0 font-mono text-[13px] tracking-wide text-plate-300">
                    RIDER · MILEAGE · SERVICE TRACKING — HARARE OPS
                </p>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <span class="mr-3 font-mono text-[11px] uppercase tracking-widest text-plate-300">
                    {{ auth()->user()->name }}
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
                    ['dashboard', 'Dashboard'],
                    ['readings.index', 'Log Reading'],
                    ['riders.index', 'Riders'],
                    ['bikes.index', 'Bikes'],
                ];
            @endphp
            @foreach ($tabs as [$route, $label])
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
    InGo Fleet Log — {{ config('app.name') }} · {{ now()->format('D d M Y, H:i') }} Harare
</footer>

</body>
</html>
