@extends('layouts.app')
@section('title', 'Bikes')

@section('content')

<h2 class="m-0 font-display text-xl uppercase tracking-wide">Bikes</h2>
<p class="mb-5 mt-1 text-[13px] text-plate-300">Bike details, service interval and assigned rider.</p>

@can('manage-fleet')
<x-panel :title="$editing ? 'Edit Bike' : 'Add Bike'"
         subtitle="Mileage is not set here — it comes from the readings, so a correction there fixes everything downstream.">
    <form method="POST" action="{{ $editing ? route('bikes.update', $editing) : route('bikes.store') }}">
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4">
            <x-field label="Registration number" name="reg" required :value="$editing?->reg" placeholder="e.g. AEJ 1234" />
            <x-field label="Model" name="model" :value="$editing?->model" placeholder="e.g. TVS HLX 150" />

            <x-select-field label="Assigned rider" name="rider_id">
                <option value="">— Unassigned —</option>
                @foreach ($riders as $rider)
                    <option value="{{ $rider->id }}" @selected(old('rider_id', $editing?->rider_id) == $rider->id)>
                        {{ $rider->name }}
                    </option>
                @endforeach
            </x-select-field>

            <x-field label="Service interval (km)" name="service_interval_km" type="number" required
                     :value="$editing?->service_interval_km ?? \App\Support\Settings::int('default_service_interval_km')" />

            <x-field label="Service interval (months)" name="service_interval_months" type="number"
                     :value="$editing ? $editing->service_interval_months : \App\Support\Settings::get('default_service_interval_months')"
                     hint="Whichever falls due first wins. Blank tracks distance only." />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <x-btn variant="primary">{{ $editing ? 'Save Bike' : 'Add Bike' }}</x-btn>
            @if ($editing)
                <x-btn variant="ghost" :href="route('bikes.index')">Cancel Edit</x-btn>
            @endif
        </div>
    </form>
</x-panel>
@endcan

<x-panel flush>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-asphalt-600 px-4 py-3.5 sm:px-5">
        <h2 class="m-0 font-display text-[15px] uppercase tracking-wide">
            {{ $bikes->count() }} {{ Str::plural('bike', $bikes->count()) }}
        </h2>
        <form method="GET" action="{{ route('bikes.index') }}" class="flex w-full gap-2 sm:w-auto">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search reg, model, rider"
                   class="min-w-0 flex-1 rounded-sm border border-asphalt-600 bg-asphalt-900 px-3 py-2 text-base text-plate-50 outline-none placeholder:text-plate-300/40 focus:border-ingo-500 sm:w-56 sm:flex-none sm:py-1.5 sm:text-[13px]">
            <x-btn variant="small">Search</x-btn>
            @if ($search)
                <x-btn variant="ghost" :href="route('bikes.index')">Clear</x-btn>
            @endif
        </form>
    </div>

    @if ($bikes->isEmpty())
        <p class="m-0 px-5 py-8 text-center text-[14px] text-plate-300">
            @if ($search)
                No bikes match that search.
            @else
                @can('manage-fleet')
                    No bikes yet. Add your first bike above.
                @else
                    No bikes have been added yet.
                @endcan
            @endif
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="table-cards w-full border-collapse text-[14px]">
                <thead>
                    <tr class="bg-asphalt-900 text-left font-mono text-[10px] uppercase tracking-widest text-plate-300">
                        <th class="px-5 py-2.5 font-semibold">Reg No.</th>
                        <th class="px-5 py-2.5 font-semibold">Model</th>
                        <th class="px-5 py-2.5 font-semibold">Rider</th>
                        <th class="px-5 py-2.5 font-semibold">Mileage</th>
                        <th class="px-5 py-2.5 font-semibold">Next Service</th>
                        <th class="px-5 py-2.5 font-semibold">Status</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bikes as $bike)
                        <tr class="border-t border-asphalt-600 {{ $editing?->is($bike) ? 'bg-asphalt-700' : '' }}">
                            <td data-label="Reg No." class="px-5 py-3 font-mono font-bold tracking-wider">{{ $bike->reg }}</td>
                            <td data-label="Model" class="px-5 py-3 text-plate-300">{{ $bike->model ?: '—' }}</td>
                            <td data-label="Rider" class="px-5 py-3">{{ $bike->rider?->name ?? '— Unassigned —' }}</td>
                            <td data-label="Mileage" class="px-5 py-3 font-mono tabular-nums">{{ number_format($bike->currentMileage()) }} km</td>
                            <td data-label="Next Service" class="px-5 py-3 font-mono tabular-nums text-plate-300">{{ number_format($bike->nextServiceAtKm()) }} km</td>
                            <td data-label="Status" class="px-5 py-3"><x-status-badge :status="$bike->serviceStatus()" /></td>
                            <td class="actions px-5 py-3">
                                @can('manage-fleet')
                                    <div class="flex justify-end gap-2">
                                        <x-btn variant="small" :href="route('bikes.index', ['edit' => $bike->id])">Edit</x-btn>
                                        <form method="POST" action="{{ route('bikes.destroy', $bike) }}"
                                              data-confirm='Remove {{ $bike->reg }} from the fleet? Its readings and service history are kept.'>
                                            @csrf @method('DELETE')
                                            <x-btn variant="danger">Remove</x-btn>
                                        </form>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel>

@endsection
