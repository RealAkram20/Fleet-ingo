@props(['title' => null, 'subtitle' => null, 'flush' => false])

<section {{ $attributes->class('mb-5 rounded-sm border border-asphalt-600 bg-asphalt-800') }}>
    @if ($title)
        <header class="{{ $flush ? 'border-b border-asphalt-600 px-5 py-4' : 'px-5 pb-0 pt-4' }}">
            <h2 class="m-0 font-display text-[15px] uppercase tracking-wide">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mb-0 mt-1 text-[13px] text-plate-300">{{ $subtitle }}</p>
            @endif
        </header>
    @endif

    <div class="{{ $flush ? '' : 'p-5' }}">
        {{ $slot }}
    </div>
</section>
