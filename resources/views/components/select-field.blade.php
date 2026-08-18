@props(['label', 'name', 'required' => false, 'hint' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block font-mono text-[10px] uppercase tracking-widest text-plate-300">
        {{ $label }}@if ($required)<span class="ml-1 text-ingo-500">*</span>@endif
    </label>

    <select id="{{ $name }}"
            name="{{ $name }}"
            {{ $attributes->class([
                'w-full rounded-sm border bg-asphalt-900 px-3 py-2 text-[14px] text-plate-50 outline-none transition',
                'focus:border-ingo-500 focus:ring-1 focus:ring-ingo-500',
                'border-status-bad' => $errors->has($name),
                'border-asphalt-600' => ! $errors->has($name),
            ]) }}>
        {{ $slot }}
    </select>

    @if ($hint && ! $errors->has($name))
        <p class="mb-0 mt-1 text-[12px] text-plate-300/70">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mb-0 mt-1 text-[12px] text-status-bad">{{ $message }}</p>
    @enderror
</div>
