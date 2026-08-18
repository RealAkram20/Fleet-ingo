@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-sm border border-status-ok/40 bg-status-ok-dim px-3 py-2 text-[13px] text-status-ok']) }}>
        {{ $status }}
    </div>
@endif
