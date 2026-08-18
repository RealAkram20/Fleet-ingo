@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-mono text-[10px] uppercase tracking-widest text-plate-300']) }}>
    {{ $value ?? $slot }}
</label>
