@props(['status'])

@php
    $classes = match ($status['level']) {
        'bad' => 'bg-status-bad-dim text-status-bad border-status-bad/40',
        'warn' => 'bg-status-warn-dim text-status-warn border-status-warn/40',
        default => 'bg-status-ok-dim text-status-ok border-status-ok/40',
    };
@endphp

<span {{ $attributes->class([
    'inline-block whitespace-nowrap rounded-sm border px-2 py-[3px] font-mono text-[10px] font-bold uppercase tracking-widest',
    $classes,
]) }}>{{ $status['label'] }}</span>
