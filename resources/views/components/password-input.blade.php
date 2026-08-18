@props(['disabled' => false])

{{--
    A password box with a show/hide control.

    The button is type="button" so it never submits the form, and it swaps the
    input's own type rather than rendering a second field, so autofill and
    password managers still see one input.
--}}
<div x-data="{ show: false }" class="relative">
    <input @disabled($disabled)
           x-bind:type="show ? 'text' : 'password'"
           type="password"
           {{ $attributes->merge(['class' =>
               'w-full rounded-sm border border-asphalt-600 bg-asphalt-900 py-2 pl-3 pr-11 text-[14px] text-plate-50 outline-none transition placeholder:text-plate-300/40 focus:border-ingo-500 focus:ring-1 focus:ring-ingo-500 disabled:opacity-50'
           ]) }}>

    <button type="button"
            x-on:click="show = !show"
            x-bind:aria-pressed="show ? 'true' : 'false'"
            x-bind:aria-label="show ? 'Hide password' : 'Show password'"
            aria-label="Show password"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center border-none bg-transparent text-plate-300 transition hover:text-ingo-500 focus:text-ingo-500 focus:outline-none">

        {{-- eye: password hidden --}}
        <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             class="h-4.5 w-4.5" aria-hidden="true">
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>

        {{-- eye with a stroke through it: password visible --}}
        <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
             class="h-4.5 w-4.5" aria-hidden="true">
            <path d="M10.7 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a18.3 18.3 0 0 1-2.7 3.7"/>
            <path d="M6.6 6.6A18 18 0 0 0 2 12s3.6 7 10 7a10.3 10.3 0 0 0 5.4-1.5"/>
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
            <line x1="3" y1="3" x2="21" y2="21"/>
        </svg>
    </button>
</div>
