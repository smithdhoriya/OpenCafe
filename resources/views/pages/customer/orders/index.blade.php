<?php

use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.customer');
title('My Orders — OpenCafe');

$orders = computed(function () {
    // Scoped to the authenticated user: no relations are loaded here since the
    // list only needs columns already present on the order row itself.
    return auth()->user()->orders()
        ->orderByDesc('placed_at')
        ->orderByDesc('id')
        ->get();
});

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
    <div class="mb-8 flex flex-col gap-1">
        <flux:heading size="xl">My orders</flux:heading>
        <p class="text-espresso-600 dark:text-cream-300">A history of everything you've ordered from us.</p>
    </div>

    @if ($this->orders->isEmpty())
        <div class="relative flex flex-col items-center gap-5 overflow-hidden rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-16 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <div class="relative flex size-20 items-center justify-center rounded-full bg-orange-100 text-orange-700 shadow-sm dark:bg-espresso-700 dark:text-orange-400">
                <flux:icon.shopping-bag variant="outline" class="size-9" />
            </div>

            <div class="relative flex flex-col gap-2">
                <flux:heading size="xl">No orders yet</flux:heading>
                <p class="max-w-md text-espresso-600 dark:text-cream-300">
                    Once you place an order, you'll be able to track it here.
                </p>
            </div>

            <flux:button as="a" href="{{ route('menu.index') }}" wire:navigate variant="primary" class="relative">
                Browse the menu
            </flux:button>
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->orders as $order)
                <div wire:key="order-{{ $order->id }}" class="flex flex-col gap-3 rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ $order->order_number }}</flux:heading>
                            <flux:badge color="orange" size="sm">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</flux:badge>
                        </div>

                        <span class="text-sm text-espresso-600 dark:text-cream-300">
                            {{ ucfirst(str_replace('_', ' ', $order->type->value)) }} &middot; {{ $order->placed_at?->format('d M Y, h:i A') }}
                        </span>
                    </div>

                    <div class="flex items-center gap-4">
                        <x-customer.price :amount="$order->total" />

                        <flux:button as="a" href="{{ route('orders.show', $order) }}" wire:navigate variant="ghost" size="sm">
                            View order
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
