@extends('layouts.app')
@section('title', 'Riders')

@section('content')

<h2 class="m-0 font-display text-xl uppercase tracking-wide">Riders</h2>
<p class="mb-5 mt-1 text-[13px] text-plate-300">Rider details and licence expiry.</p>

@can('manage-fleet')
<x-panel :title="$editing ? 'Edit Rider' : 'Add Rider'">
    <form method="POST" action="{{ $editing ? route('riders.update', $editing) : route('riders.store') }}">
        @csrf
        @if ($editing) @method('PATCH') @endif

        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,220px),1fr))] gap-4">
            <x-field label="Full name" name="name" required :value="$editing?->name" placeholder="Rider name" />
            <x-field label="Phone" name="phone" :value="$editing?->phone" placeholder="e.g. 0771234567" />
            <x-field label="Licence number" name="license_number" :value="$editing?->license_number" placeholder="Licence no." />
            <x-field label="Licence expiry date" name="license_expiry" type="date"
                     :value="$editing?->license_expiry?->toDateString()"
                     hint="Leave blank if not yet on file." />
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <x-btn variant="primary">{{ $editing ? 'Save Rider' : 'Add Rider' }}</x-btn>
            @if ($editing)
                <x-btn variant="ghost" :href="route('riders.index')">Cancel Edit</x-btn>
            @endif
        </div>
    </form>
</x-panel>
@endcan

<x-panel flush>
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-asphalt-600 px-4 py-3.5 sm:px-5">
        <h2 class="m-0 font-display text-[15px] uppercase tracking-wide">
            {{ $riders->count() }} {{ Str::plural('rider', $riders->count()) }}
        </h2>
        <form method="GET" action="{{ route('riders.index') }}" class="flex w-full gap-2 sm:w-auto">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search name, phone, licence"
                   class="min-w-0 flex-1 rounded-sm border border-asphalt-600 bg-asphalt-900 px-3 py-2 text-base text-plate-50 outline-none placeholder:text-plate-300/40 focus:border-ingo-500 sm:w-56 sm:flex-none sm:py-1.5 sm:text-[13px]">
            <x-btn variant="small">Search</x-btn>
            @if ($search)
                <x-btn variant="ghost" :href="route('riders.index')">Clear</x-btn>
            @endif
        </form>
    </div>

    @if ($riders->isEmpty())
        <p class="m-0 px-5 py-8 text-center text-[14px] text-plate-300">
            @if ($search)
                No riders match that search.
            @else
                @can('manage-fleet')
                    No riders yet. Add your first rider above.
                @else
                    No riders have been added yet.
                @endcan
            @endif
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="table-cards w-full border-collapse text-[14px]">
                <thead>
                    <tr class="bg-asphalt-900 text-left font-mono text-[10px] uppercase tracking-widest text-plate-300">
                        <th class="px-5 py-2.5 font-semibold">Name</th>
                        <th class="px-5 py-2.5 font-semibold">Phone</th>
                        <th class="px-5 py-2.5 font-semibold">Licence No.</th>
                        <th class="px-5 py-2.5 font-semibold">Expiry</th>
                        <th class="px-5 py-2.5 font-semibold">Status</th>
                        <th class="px-5 py-2.5 font-semibold">Bikes</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($riders as $rider)
                        <tr class="border-t border-asphalt-600 {{ $editing?->is($rider) ? 'bg-asphalt-700' : '' }}">
                            <td data-label="Name" class="px-5 py-3">{{ $rider->name }}</td>
                            <td data-label="Phone" class="px-5 py-3 font-mono tabular-nums text-plate-300">{{ $rider->phone ?: '—' }}</td>
                            <td data-label="Licence No." class="px-5 py-3 font-mono text-plate-300">{{ $rider->license_number ?: '—' }}</td>
                            <td data-label="Expiry" class="px-5 py-3 tabular-nums">{{ $rider->license_expiry?->format('d M Y') ?? '—' }}</td>
                            <td data-label="Status" class="px-5 py-3"><x-status-badge :status="$rider->licenseStatus()" /></td>
                            <td data-label="Bikes" class="px-5 py-3 tabular-nums text-plate-300">{{ $rider->bikes_count }}</td>
                            <td class="actions px-5 py-3">
                                @can('manage-fleet')
                                    <div class="flex justify-end gap-2">
                                        <x-btn variant="small" :href="route('riders.index', ['edit' => $rider->id])">Edit</x-btn>
                                        <form method="POST" action="{{ route('riders.destroy', $rider) }}"
                                              data-confirm='Remove {{ $rider->name }} from the roster? Their bikes stay assigned and their history is kept.'>
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
