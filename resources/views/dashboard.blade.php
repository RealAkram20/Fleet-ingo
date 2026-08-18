@extends('layouts.app')
@section('title', 'Fleet Dashboard')

@section('content')

<div class="mb-1 flex flex-wrap items-baseline justify-between gap-3">
    <h2 class="m-0 font-display text-xl uppercase tracking-wide">Fleet Dashboard</h2>
    @if ($filter)
        <a href="{{ route('dashboard') }}" class="font-mono text-[11px] uppercase tracking-widest text-ingo-500 hover:underline">
            Clear filter · showing {{ $bikes->count() }} of {{ $summary['total'] }}
        </a>
    @endif
</div>
<p class="mb-5 mt-0 text-[13px] text-plate-300">Live status across every bike in the fleet.</p>

{{-- Summary strip. Each tile that represents a problem links to the filtered view. --}}
<div class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-2.5">
    @php
        $tiles = [
            ['value' => $summary['total'], 'label' => 'Bikes in fleet', 'tone' => 'plain', 'href' => route('dashboard')],
            ['value' => $summary['overdue'], 'label' => 'Service overdue', 'tone' => 'bad', 'href' => route('dashboard', ['status' => 'bad'])],
            ['value' => $summary['due_soon'], 'label' => 'Service due soon', 'tone' => 'warn', 'href' => route('dashboard', ['status' => 'warn'])],
            ['value' => $summary['licence_flags'], 'label' => 'Licence flags', 'tone' => $summary['licence_flags'] > 0 ? 'warn' : 'ok', 'href' => route('riders.index')],
            ['value' => $summary['missed_readings'], 'label' => 'No reading this week', 'tone' => $summary['missed_readings'] > 0 ? 'warn' : 'ok', 'href' => route('readings.index')],
        ];
    @endphp

    @foreach ($tiles as $tile)
        @php
            $tone = match ($tile['tone']) {
                'bad' => 'border-l-status-bad',
                'warn' => 'border-l-status-warn',
                'ok' => 'border-l-status-ok',
                default => 'border-l-ingo-500',
            };
        @endphp
        <a href="{{ $tile['href'] }}"
           class="block rounded-sm border border-asphalt-600 border-l-[3px] {{ $tone }} bg-asphalt-800 px-4 py-3.5 transition hover:bg-asphalt-700">
            <div class="font-display text-[28px] font-semibold leading-none tabular-nums">{{ $tile['value'] }}</div>
            <div class="mt-1.5 font-mono text-[10px] uppercase tracking-widest text-plate-300">{{ $tile['label'] }}</div>
        </a>
    @endforeach
</div>

@if ($bikes->isEmpty())
    <x-panel>
        <p class="m-0 text-center text-[14px] text-plate-300">
            @if ($filter)
                No bikes match this filter.
            @else
                No bikes yet. <a href="{{ route('bikes.index') }}" class="text-ingo-500 hover:underline">Add your first bike.</a>
            @endif
        </p>
    </x-panel>
@else
    <div class="grid grid-cols-[repeat(auto-fill,minmax(320px,1fr))] gap-4">
        @foreach ($bikes as $bike)
            @php
                $service = $bike->serviceStatus();
                $licence = $bike->rider?->licenseStatus() ?? ['level' => 'warn', 'label' => 'No rider'];
                $stale = ! $bike->last_reading_on || $bike->last_reading_on < now()->startOfWeek()->toDateString();
            @endphp

            <article class="overflow-hidden rounded-sm border border-asphalt-600 bg-asphalt-800">
                {{-- Plate strip, carried over from the original --}}
                <div class="flex items-center justify-between gap-3 border-b-2 border-asphalt-900 bg-plate-50 px-3.5 py-2">
                    <span class="font-mono text-[17px] font-bold tracking-widest text-asphalt-900">{{ $bike->reg }}</span>
                    <x-status-badge :status="$service" />
                </div>

                <div class="p-4">
                    <p class="mb-3 mt-0 font-display text-[15px] uppercase tracking-wide text-plate-300">{{ $bike->model ?: '—' }}</p>

                    <x-odometer :mileage="$bike->currentMileage()" />
                    <p class="mb-0 mt-1.5 font-mono text-[10px] uppercase tracking-widest text-plate-300">
                        Current mileage (km)
                        @if ($stale)
                            <span class="ml-1 text-status-warn">· no reading this week</span>
                        @endif
                    </p>

                    <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-asphalt-600 pt-3">
                        <a href="{{ route('riders.index', ['edit' => $bike->rider_id]) }}"
                           class="text-[14px] {{ $bike->rider ? 'text-plate-50 hover:text-ingo-500' : 'text-plate-300' }}">
                            {{ $bike->rider?->name ?? '— Unassigned —' }}
                        </a>
                        <x-status-badge :status="$licence" />
                    </div>

                    <dl class="mt-3 space-y-1 text-[13px] text-plate-300">
                        <div class="flex justify-between gap-3">
                            <dt>Next service</dt>
                            <dd class="m-0 tabular-nums text-plate-50">
                                {{ number_format($bike->nextServiceAtKm()) }} km
                                <span class="{{ $service['km_remaining'] <= 0 ? 'text-status-bad' : 'text-plate-300' }}">
                                    ({{ $service['km_remaining'] <= 0
                                        ? number_format(abs($service['km_remaining'])).' over'
                                        : number_format($service['km_remaining']).' left' }})
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt>Last serviced</dt>
                            <dd class="m-0 tabular-nums text-plate-50">
                                {{ $bike->lastServicedOn()?->format('d M Y') ?? 'Never' }}
                                @if ($bike->lastServiceMileage())
                                    <span class="text-plate-300">at {{ number_format($bike->lastServiceMileage()) }} km</span>
                                @endif
                            </dd>
                        </div>
                        @if ($service['days_remaining'] !== null)
                            <div class="flex justify-between gap-3">
                                <dt>Time interval</dt>
                                <dd class="m-0 tabular-nums {{ $service['days_remaining'] <= 0 ? 'text-status-bad' : 'text-plate-50' }}">
                                    {{ $service['days_remaining'] <= 0
                                        ? abs($service['days_remaining']).' days overdue'
                                        : $service['days_remaining'].' days left' }}
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-asphalt-600 pt-3.5">
                        <x-btn variant="ghost" :href="route('readings.index', ['bike' => $bike->id])">Log Reading</x-btn>

                        <form method="POST" action="{{ route('services.store', $bike) }}"
                              onsubmit="return confirm('Log a service for {{ $bike->reg }} today at {{ number_format($bike->currentMileage()) }} km?')">
                            @csrf
                            <input type="hidden" name="serviced_on" value="{{ now()->toDateString() }}">
                            <input type="hidden" name="mileage" value="{{ $bike->currentMileage() }}">
                            <x-btn variant="primary">Mark Serviced</x-btn>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif

@endsection
