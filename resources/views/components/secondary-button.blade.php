<button {{ $attributes->merge(['type' => 'button', 'class' =>
    'inline-block cursor-pointer rounded-sm border border-asphalt-600 bg-transparent px-4 py-2 font-display text-[13px] font-medium uppercase tracking-wide text-plate-300 transition hover:border-plate-300 hover:text-plate-50'
]) }}>{{ $slot }}</button>
