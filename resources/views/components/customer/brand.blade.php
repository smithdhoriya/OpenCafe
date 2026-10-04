@props([
    'tagline' => true,
    'inverted' => false,
])

<div {{ $attributes->class('flex items-center gap-2.5') }}>
    <div @class([
        'flex aspect-square size-10 items-center justify-center rounded-2xl shadow-sm',
        'bg-orange-500 text-espresso-900' => $inverted,
        'bg-espresso-800 text-cream-50 dark:bg-orange-500 dark:text-espresso-900' => ! $inverted,
    ])>
        <x-customer.brand-mark class="size-5" />
    </div>

    <div class="flex flex-col leading-none">
        <span @class([
            'text-lg font-bold tracking-tight',
            'text-cream-50' => $inverted,
            'text-espresso-900 dark:text-cream-50' => ! $inverted,
        ])>
            Open<span @class(['text-orange-400' => $inverted, 'text-accent-content' => ! $inverted])>Cafe</span>
        </span>

        @if ($tagline)
            <span @class([
                'text-[10px] font-medium tracking-[0.2em] uppercase',
                'text-cream-400' => $inverted,
                'text-espresso-400 dark:text-cream-400' => ! $inverted,
            ])>
                Est. Local Roast
            </span>
        @endif
    </div>
</div>
