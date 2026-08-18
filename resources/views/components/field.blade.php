@props(['label', 'name', 'type' => 'text', 'value' => null, 'placeholder' => null, 'required' => false, 'hint' => null])

<div>
    <label for="{{ $name }}" class="mb-1.5 block font-mono text-[10px] uppercase tracking-widest text-plate-300">
        {{ $label }}@if ($required)<span class="ml-1 text-ingo-500">*</span>@endif
    </label>

    @if ($type === 'password')
        {{--
            Password boxes get a show/hide control; the component owns the styling.

            No @if inside this tag: Blade's component-tag compiler cannot parse a
            directive in the attribute list and silently emits the tag as text.
        --}}
        <x-password-input
            id="{{ $name }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            :class="$errors->has($name) ? 'border-status-bad' : ''"
            {{ $attributes }} />
    @else
        <input type="{{ $type }}"
               id="{{ $name }}"
               name="{{ $name }}"
               @if ($type !== 'file') value="{{ old($name, $value) }}" @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               {{ $attributes->class([
                   'w-full rounded-sm border bg-asphalt-900 px-3 py-2 text-base text-plate-50 outline-none transition sm:text-[14px]',
                   'placeholder:text-plate-300/40 focus:border-ingo-500 focus:ring-1 focus:ring-ingo-500',
                   'file:mr-3 file:cursor-pointer file:rounded-sm file:border-0 file:bg-asphalt-700 file:px-3 file:py-1.5 file:font-mono file:text-[11px] file:uppercase file:tracking-widest file:text-plate-300' => $type === 'file',
                   'border-status-bad' => $errors->has($name),
                   'border-asphalt-600' => ! $errors->has($name),
               ]) }}>
    @endif

    @if ($hint && ! $errors->has($name))
        <p class="mb-0 mt-1 text-[12px] text-plate-300/70">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mb-0 mt-1 text-[12px] text-status-bad">{{ $message }}</p>
    @enderror
</div>
