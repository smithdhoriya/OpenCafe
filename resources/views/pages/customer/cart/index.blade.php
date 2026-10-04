<?php

use App\Models\MenuCategory;
use function Livewire\Volt\{layout, state, title};

layout('components.layouts.customer');
title('Your Cart — OpenCafe');

state([
    'categories' => fn () => MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->limit(4)->get(),
]);

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
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
</div>
