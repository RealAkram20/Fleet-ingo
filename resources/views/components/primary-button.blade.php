<button {{ $attributes->merge(['type' => 'submit', 'class' =>
    'inline-block cursor-pointer rounded-sm border border-ingo-500 bg-ingo-500 px-4 py-2 font-display text-[13px] font-bold uppercase tracking-wide text-asphalt-900 transition hover:brightness-110 disabled:opacity-50'
]) }}>{{ $slot }}</button>
