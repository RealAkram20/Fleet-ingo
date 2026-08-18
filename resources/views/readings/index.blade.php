@extends('layouts.app')
@section('title', 'Log Reading')

@section('content')

<h2 class="m-0 font-display text-xl uppercase tracking-wide">Log Weekly Reading</h2>
<p class="mb-5 mt-1 text-[13px] text-plate-300">Record this week's odometer reading for a rider's bike.</p>

@if ($bikes->isEmpty())
    <x-panel>
        <p class="m-0 text-center text-[14px] text-plate-300">
            No bikes yet. <a href="{{ route('bikes.index') }}" class="text-ingo-500 hover:underline">Add one first.</a>
        </p>
    </x-panel>
@else

    <x-panel :title="$editing ? 'Correct Reading' : 'New Reading'"
             :subtitle="$editing ? 'Editing the reading from '.$editing->recorded_on->format('d M Y').'.' : null">

        <form method="POST"
              action="{{ $editing ? route('readings.update', $editing) : route('readings.store') }}">
            @csrf
            @if ($editing) @method('PATCH') @endif

            <div class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-4">
                <x-select-field label="Bike" name="bike_id" required
                                onchange="if(!this.dataset.editing) window.location = '{{ route('readings.index') }}?bike=' + this.value"
                                data-editing="{{ $editing ? '1' : '' }}">
                    @foreach ($bikes as $bike)
                        <option value="{{ $bike->id }}"
                                @selected(old('bike_id', $editing?->bike_id ?? $selected?->id) == $bike->id)>
                            {{ $bike->reg }} — {{ $bike->rider?->name ?? 'Unassigned' }}
                        </option>
                    @endforeach
                </x-select-field>

                <x-field label="Reading date" name="recorded_on" type="date" required
                         :value="$editing?->recorded_on->toDateString() ?? now()->toDateString()"
                         max="{{ now()->toDateString() }}" />

                <x-field label="Odometer reading (km)" name="mileage" type="number" required
                         :value="$editing?->mileage"
                         placeholder="e.g. 14520"
                         :hint="$selected && ! $editing && $selected->currentMileage()
                            ? 'Currently on file: '.number_format($selected->currentMileage()).' km'
                            : null" />
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-btn variant="primary">{{ $editing ? 'Save Correction' : 'Save Reading' }}</x-btn>
                @if ($editing)
                    <x-btn variant="ghost" :href="route('readings.index', ['bike' => $editing->bike_id])">Cancel</x-btn>
                @endif
            </div>
        </form>
    </x-panel>

    @if ($selected)
        <x-panel flush
                 :title="'Recent Readings — '.$selected->reg"
                 subtitle="A mistyped reading can be corrected here; the fleet figures follow automatically.">

            @if ($history->isEmpty())
                <p class="m-0 px-5 py-6 text-center text-[14px] text-plate-300">
                    No readings logged for this bike yet.
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-[14px]">
                        <thead>
                            <tr class="bg-asphalt-900 text-left font-mono text-[10px] uppercase tracking-widest text-plate-300">
                                <th class="px-5 py-2.5 font-semibold">Date</th>
                                <th class="px-5 py-2.5 font-semibold">Odometer</th>
                                <th class="px-5 py-2.5 font-semibold">Since previous</th>
                                <th class="px-5 py-2.5 font-semibold">Logged by</th>
                                <th class="px-5 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $i => $reading)
                                @php $previous = $history[$i + 1] ?? null; @endphp
                                <tr class="border-t border-asphalt-600">
                                    <td class="px-5 py-3 tabular-nums">{{ $reading->recorded_on->format('d M Y') }}</td>
                                    <td class="px-5 py-3 font-mono tabular-nums">{{ number_format($reading->mileage) }} km</td>
                                    <td class="px-5 py-3 tabular-nums text-plate-300">
                                        {{ $previous ? '+'.number_format($reading->mileage - $previous->mileage).' km' : '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-plate-300">{{ $reading->recorder?->name ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex justify-end gap-2">
                                            <x-btn variant="small" :href="route('readings.index', ['bike' => $selected->id, 'edit' => $reading->id])">Edit</x-btn>
                                            <form method="POST" action="{{ route('readings.destroy', $reading) }}"
                                                  onsubmit="return confirm('Delete the reading of {{ number_format($reading->mileage) }} km from {{ $reading->recorded_on->format('d M Y') }}?')">
                                                @csrf @method('DELETE')
                                                <x-btn variant="danger">Delete</x-btn>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-panel>
    @endif
@endif

@endsection
