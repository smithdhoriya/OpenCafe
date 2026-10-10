{{-- Reuses Flux's existing appearance system ($flux.appearance), which already persists
     to localStorage and avoids a flash of the wrong theme via @fluxAppearance in <head>. --}}
<button
    type="button"
    x-data
    x-on:click="$flux.appearance = document.documentElement.classList.contains('dark') ? 'light' : 'dark'"
    {{ $attributes->class('flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-200/60 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-700/60 dark:hover:text-zinc-100') }}
    aria-label="Toggle light and dark theme"
>
    <flux:icon icon="sun" variant="outline" class="size-5 dark:hidden" />
    <flux:icon icon="moon" variant="outline" class="hidden size-5 dark:block" />
</button>
