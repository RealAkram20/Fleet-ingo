<button {{ $attributes->merge(['type' => 'submit', 'class' =>
    'inline-block cursor-pointer rounded-sm border border-status-bad/50 bg-status-bad-dim px-4 py-2 font-display text-[13px] font-medium uppercase tracking-wide text-status-bad transition hover:border-status-bad'
]) }}>{{ $slot }}</button>
