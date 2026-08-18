@props(['variant' => 'primary', 'as' => 'button', 'href' => null])

@php
    $base = 'inline-block cursor-pointer rounded-sm border font-display text-[13px] uppercase tracking-wide transition disabled:opacity-50';
    $size = 'px-4 py-2';
    $look = match ($variant) {
        'primary' => 'border-ingo-500 bg-ingo-500 font-bold text-asphalt-900 hover:brightness-110',
        'danger' => 'border-status-bad/50 bg-status-bad-dim font-medium text-status-bad hover:border-status-bad',
        'small' => 'border-asphalt-600 bg-asphalt-700 font-medium text-plate-300 hover:text-plate-50',
        default => 'border-asphalt-600 bg-transparent font-medium text-plate-300 hover:border-plate-300 hover:text-plate-50',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$base, $size, $look]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class([$base, $size, $look]) }}>{{ $slot }}</button>
@endif
