@props([
    'offer',
])

<flux:card {{ $attributes->class('group relative flex flex-col gap-4 overflow-hidden border-orange-200 bg-linear-to-br from-orange-50 to-cream-50 p-6 shadow-xs transition duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-orange-600/30 dark:from-espresso-800 dark:to-espresso-800') }}>
    <div class="pointer-events-none absolute -top-6 -right-6 size-24 rounded-full bg-orange-300/25 blur-2xl dark:bg-orange-500/10"></div>

    <div class="relative flex items-start justify-between gap-3">
        <div class="flex size-11 items-center justify-center rounded-xl bg-orange-500/15 text-orange-600 dark:bg-orange-400/15 dark:text-orange-400">
            <flux:icon.tag variant="outline" class="size-5" />
        </div>

        <span class="text-xl font-bold text-accent-content">
            @if ($offer->type === \App\Enums\OfferType::Percentage)
                {{ rtrim(rtrim($offer->value, '0'), '.') }}% off
            @else
                <x-customer.currency-symbol />{{ number_format($offer->value, 2) }} off
            @endif
        </span>
    </div>

    <div class="relative flex flex-col gap-1">
        <flux:heading size="lg">{{ $offer->name }}</flux:heading>

        @if ($offer->description)
            <p class="text-sm text-espresso-600 dark:text-cream-300">{{ $offer->description }}</p>
        @endif
    </div>

    <div class="relative mt-1 flex items-center justify-between gap-3 border-t border-orange-200/60 pt-4 dark:border-orange-600/20">
        <flux:badge color="orange" class="w-fit font-mono">{{ $offer->code }}</flux:badge>

        <div class="flex flex-col items-end gap-0.5 text-xs text-espresso-500 dark:text-cream-300">
            @if ($offer->min_order_total)
                <span>Min. order <x-customer.currency-symbol />{{ number_format($offer->min_order_total, 2) }}</span>
            @endif

            @if ($offer->ends_at)
                <span>Ends {{ $offer->ends_at->format('M j, Y') }}</span>
            @endif
        </div>
    </div>
</flux:card>
