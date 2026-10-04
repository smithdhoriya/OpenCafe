{{-- Reuses Flux's existing appearance system ($flux.appearance), which already persists
     to localStorage and avoids a flash of the wrong theme via @fluxAppearance in <head>. --}}
<button
    type="button"
    x-data
    x-on:click="$flux.appearance = document.documentElement.classList.contains('dark') ? 'light' : 'dark'"
    {{ $attributes->class('flex size-10 items-center justify-center rounded-full text-espresso-600 transition hover:bg-espresso-100 dark:text-cream-200 dark:hover:bg-espresso-700') }}
    aria-label="Toggle light and dark theme"
>
    <flux:icon icon="sun" variant="outline" class="size-5 dark:hidden" />
    <flux:icon icon="moon" variant="outline" class="hidden size-5 dark:block" />
</button>
