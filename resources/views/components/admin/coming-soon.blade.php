@props([
    'label',
])

<div class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-24 text-center dark:border-zinc-700 dark:bg-zinc-900/40">
    <div class="flex size-14 items-center justify-center rounded-full bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
        <flux:icon.wrench variant="outline" class="size-6" />
    </div>

    <flux:heading size="lg">{{ $label }}</flux:heading>
    <p class="max-w-sm text-zinc-500 dark:text-zinc-400">This section is coming soon.</p>
</div>
