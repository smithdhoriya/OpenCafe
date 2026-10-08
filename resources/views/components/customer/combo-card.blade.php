@props([
    'combo',
])

<flux:card {{ $attributes->class('group flex h-full flex-col gap-0 overflow-hidden border-espresso-100 bg-white p-0 shadow-xs transition duration-300 hover:-translate-y-1.5 hover:shadow-xl dark:border-espresso-700 dark:bg-espresso-800') }}>
    <div class="relative flex aspect-[16/9] items-center justify-center overflow-hidden bg-linear-to-br from-orange-100 via-orange-50 to-espresso-200 dark:from-espresso-700 dark:via-espresso-800 dark:to-espresso-900">
        <flux:icon.gift variant="outline" class="size-14 text-espresso-400/50 transition duration-300 group-hover:scale-110 dark:text-cream-100/25" />

        <flux:badge color="orange" class="absolute top-3 left-3 shadow-sm">Combo</flux:badge>
    </div>

    <div class="flex flex-1 flex-col gap-3 p-6">
        <div class="flex items-start justify-between gap-3">
            <flux:heading size="lg">{{ $combo->name }}</flux:heading>

            <x-customer.price
                :amount="$combo->price"
                :compare-at="$combo->compare_at_price"
                stacked
                class="shrink-0 text-lg text-accent-content"
            />
        </div>

        @if ($combo->description)
            <p class="text-sm text-espresso-600 dark:text-cream-300">{{ $combo->description }}</p>
        @endif

        <ul class="mt-1 flex flex-col gap-1.5 text-sm text-espresso-700 dark:text-cream-200">
            @foreach ($combo->comboItems as $comboItem)
                <li class="flex items-center gap-2">
                    <flux:icon.check variant="micro" class="shrink-0 text-accent-content" />

                    {{ $comboItem->quantity }}&times; {{ $comboItem->menuItem?->name }}

                    @if ($comboItem->variant)
                        <span class="text-espresso-400 dark:text-cream-400">({{ $comboItem->variant->name }})</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <flux:button wire:click="addComboToCart({{ $combo->id }})" variant="primary" icon="shopping-bag" class="mt-auto w-full">
            Add to cart
        </flux:button>
    </div>
</flux:card>
