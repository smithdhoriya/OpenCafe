<?php

use App\Models\Cart;
use App\Models\MenuCategory;
use function Livewire\Volt\{computed, layout, state, title};

layout('components.layouts.customer');
title('Your Cart — OpenCafe');

state([
    'categories' => fn () => MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->limit(4)->get(),
]);

$cart = computed(function () {
    return auth()->check() ? Cart::activeForUser(auth()->user()) : null;
});

$items = computed(function () {
    return $this->cart
        ?->items()
        ->with(['menuItem', 'variant', 'combo'])
        ->orderBy('id')
        ->get()
        ?? collect();
});

$subtotal = computed(function () {
    return $this->items->sum(fn ($item) => $item->line_total);
});

$findOwnedItem = function (int $cartItemId) {
    return $this->cart?->items()->whereKey($cartItemId)->first();
};

$incrementItem = function (int $cartItemId) {
    $this->findOwnedItem($cartItemId)?->increment('quantity');

    $this->dispatch('cart-updated');
};

$decrementItem = function (int $cartItemId) {
    $item = $this->findOwnedItem($cartItemId);

    if ($item && $item->quantity > 1) {
        $item->decrement('quantity');
    }

    $this->dispatch('cart-updated');
};

$removeItem = function (int $cartItemId) {
    $this->findOwnedItem($cartItemId)?->delete();

    $this->dispatch('cart-updated');
};

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
    @if (session('status'))
        <div class="mb-6 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300">
            {{ session('status') }}
        </div>
    @endif

    @if ($this->items->isEmpty())
        <div class="relative flex flex-col items-center gap-5 overflow-hidden rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-16 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <div class="pointer-events-none absolute top-1/2 left-1/2 size-56 -translate-x-1/2 -translate-y-1/2 rounded-full bg-orange-300/20 blur-3xl dark:bg-orange-500/10"></div>

            <div class="relative flex size-20 items-center justify-center rounded-full bg-orange-100 text-orange-700 shadow-sm dark:bg-espresso-700 dark:text-orange-400">
                <flux:icon.shopping-bag variant="outline" class="size-9" />
            </div>

            <div class="relative flex flex-col gap-2">
                <flux:heading size="xl">Your cart is empty</flux:heading>
                <p class="max-w-md text-espresso-600 dark:text-cream-300">
                    Browse the menu and add a few favorites — they'll show up here, ready for checkout.
                </p>
            </div>

            <flux:button as="a" href="{{ route('menu.index') }}" wire:navigate variant="primary" class="relative" icon-trailing="arrow-right">
                Browse the menu
            </flux:button>

            @if ($categories->isNotEmpty())
                <div class="relative mt-4 flex flex-wrap items-center justify-center gap-2 border-t border-espresso-200 pt-6 dark:border-espresso-700">
                    <span class="w-full text-xs font-medium tracking-wide text-espresso-500 uppercase dark:text-cream-300">
                        Popular categories
                    </span>

                    @foreach ($categories as $category)
                        <a
                            href="{{ route('menu.index', ['category' => $category->slug]) }}"
                            wire:navigate
                            class="inline-flex items-center gap-1.5 rounded-xl border border-espresso-200 bg-white px-3 py-1.5 text-sm text-espresso-700 transition hover:border-orange-300 dark:border-espresso-700 dark:bg-espresso-800 dark:text-cream-200"
                        >
                            <x-customer.category-icon :slug="$category->slug" class="size-3.5" />
                            {{ $category->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <flux:heading size="xl" class="mb-8">Your cart</flux:heading>

        <div class="flex flex-col gap-4">
            @foreach ($this->items as $item)
                <div wire:key="cart-item-{{ $item->id }}" class="flex items-center gap-4 rounded-2xl border border-espresso-100 bg-white p-4 dark:border-espresso-700 dark:bg-espresso-800/60">
                    <div class="size-20 shrink-0 overflow-hidden rounded-xl bg-linear-to-br from-orange-100 via-orange-50 to-espresso-200 dark:from-espresso-700 dark:via-espresso-800 dark:to-espresso-900">
                        <x-customer.menu-image :item="$item->isCombo() ? $item->combo : $item->menuItem" class="size-full object-cover" />
                    </div>

                    <div class="flex flex-1 flex-col gap-1">
                        <flux:heading size="sm">{{ $item->isCombo() ? $item->combo->name : $item->menuItem->name }}</flux:heading>

                        @if ($item->isCombo())
                            <span class="text-sm text-espresso-500 dark:text-cream-300">Combo</span>
                        @elseif ($item->variant)
                            <span class="text-sm text-espresso-500 dark:text-cream-300">{{ $item->variant->name }}</span>
                        @endif

                        <x-customer.price :amount="$item->unit_price" class="gap-0 text-sm text-espresso-500 dark:text-cream-300" />
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-espresso-200 bg-white px-3 py-1 dark:border-espresso-700 dark:bg-espresso-900">
                        <button type="button" wire:click="decrementItem({{ $item->id }})" class="flex size-6 items-center justify-center text-espresso-600 hover:text-accent-content dark:text-cream-200" aria-label="Decrease quantity">
                            <flux:icon.minus variant="micro" />
                        </button>
                        <span class="w-4 text-center font-medium">{{ $item->quantity }}</span>
                        <button type="button" wire:click="incrementItem({{ $item->id }})" class="flex size-6 items-center justify-center text-espresso-600 hover:text-accent-content dark:text-cream-200" aria-label="Increase quantity">
                            <flux:icon.plus variant="micro" />
                        </button>
                    </div>

                    <x-customer.price :amount="$item->line_total" class="w-20 justify-end text-base text-accent-content" />

                    <flux:button wire:click="removeItem({{ $item->id }})" variant="ghost" icon="trash" square aria-label="Remove item" />
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex items-center justify-between rounded-2xl border border-espresso-100 bg-cream-100 p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm">Subtotal</flux:heading>
            <x-customer.price :amount="$this->subtotal" class="text-xl text-accent-content" />
        </div>

        <flux:button as="a" href="{{ route('checkout.index') }}" wire:navigate variant="primary" class="mt-6 w-full">
            Proceed to checkout
        </flux:button>
    @endif
</div>
