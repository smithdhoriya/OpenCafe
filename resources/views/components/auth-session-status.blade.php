@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm font-medium text-orange-700 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-400']) }}>
        <flux:icon.information-circle variant="mini" class="mt-0.5 shrink-0" />
        <span>{{ $status }}</span>
    </div>
@endif
