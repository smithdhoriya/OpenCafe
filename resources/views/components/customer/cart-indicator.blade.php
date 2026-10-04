@props([
    'count' => 0,
])

<flux:button
    as="a"
    href="{{ route('cart.index') }}"
    wire:navigate
    icon="shopping-bag"
    variant="ghost"
    class="relative"
    aria-label="View cart"
>
    @if ($count > 0)
        <span class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-(--color-accent) text-[10px] font-semibold text-(--color-accent-foreground)">
            {{ $count }}
        </span>
    @endif
</flux:button>
