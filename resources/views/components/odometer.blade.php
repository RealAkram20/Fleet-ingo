@props(['mileage' => 0])

@php
    $digits = str_split(str_pad((string) max(0, (int) $mileage), 6, '0', STR_PAD_LEFT));
@endphp

<div {{ $attributes->class('flex gap-[3px]') }}>
    @foreach ($digits as $digit)
        <div class="rounded-[2px] border border-asphalt-600 bg-asphalt-900 px-[7px] py-1.5 text-center font-mono text-[19px] font-bold leading-none text-plate-50">{{ $digit }}</div>
    @endforeach
</div>
