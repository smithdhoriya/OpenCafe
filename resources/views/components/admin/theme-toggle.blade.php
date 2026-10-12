{{-- Reuses Flux's existing appearance system ($flux.appearance), which already persists
     to localStorage and avoids a flash of the wrong theme via @fluxAppearance in <head>. --}}
<button
    type="button"
    x-data
    x-on:click="$flux.appearance = document.documentElement.classList.contains('dark') ? 'light' : 'dark'"
    {{ $attributes->class('flex size-9 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-emerald-100/60 hover:text-emerald-700 dark:text-zinc-400 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300') }}
    aria-label="Toggle light and dark theme"
>
    <flux:icon icon="sun" variant="outline" class="size-5 dark:hidden" />
    <flux:icon icon="moon" variant="outline" class="hidden size-5 dark:block" />
</button>
