<?php

use App\Models\Cart;
use function Livewire\Volt\{computed, on};

$count = computed(function () {
    return auth()->check()
        ? (Cart::activeForUser(auth()->user())?->items()->sum('quantity') ?? 0)
        : 0;
});

on(['cart-updated' => fn () => null]);

?>

<flux:button
    as="a"
    href="{{ route('cart.index') }}"
    wire:navigate
    icon="shopping-bag"
    variant="ghost"
    class="relative"
    aria-label="View cart"
>
    @if ($this->count > 0)
        <span class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-(--color-accent) text-[10px] font-semibold text-(--color-accent-foreground)">
            {{ $this->count }}
        </span>
    @endif
</flux:button>
