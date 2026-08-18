@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' =>
    'w-full rounded-sm border border-asphalt-600 bg-asphalt-900 px-3 py-2 text-base text-plate-50 sm:text-[14px] outline-none transition placeholder:text-plate-300/40 focus:border-ingo-500 focus:ring-1 focus:ring-ingo-500 disabled:opacity-50'
]) }}>
