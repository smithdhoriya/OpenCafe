<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use function Livewire\Volt\{computed, layout, state, title};

layout('components.layouts.customer');
title('Menu — OpenCafe');

state([
    'search' => '',
    'activeCategory' => fn () => request()->query('category'),
]);

$categories = computed(function () {
    return MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->get();
});

$items = computed(function () {
    return MenuItem::query()
        ->with('category')
        ->where('is_available', true)
        ->when(
            $this->activeCategory,
            fn ($query) => $query->whereHas(
                'category',
                fn ($query) => $query->where('slug', $this->activeCategory)
            )
        )
        ->when($this->search, fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
        ->orderBy('sort_order')
        ->get();
});

$selectCategory = function (?string $slug) {
    $this->activeCategory = $slug;
};

?>

<div class="mx-auto max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
    <x-customer.section-heading
        eyebrow="Our menu"
        title="Everything on the menu"
        description="Browse by category or search for something specific."
        class="mb-10"
    />

    <div class="mb-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="selectCategory(null)"
                @class([
                    'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition',
                    'border-transparent bg-(--color-accent) text-(--color-accent-foreground) shadow-sm' => $activeCategory === null,
                    'border-espresso-200 bg-white text-espresso-700 hover:border-orange-300 dark:border-espresso-700 dark:bg-espresso-800 dark:text-cream-200' => $activeCategory !== null,
                ])
            >
                <flux:icon.squares-2x2 variant="outline" class="size-4" />
                All
            </button>

            @foreach ($this->categories as $category)
                <button
                    type="button"
                    wire:click="selectCategory('{{ $category->slug }}')"
                    @class([
                        'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition',
                        'border-transparent bg-(--color-accent) text-(--color-accent-foreground) shadow-sm' => $activeCategory === $category->slug,
                        'border-espresso-200 bg-white text-espresso-700 hover:border-orange-300 dark:border-espresso-700 dark:bg-espresso-800 dark:text-cream-200' => $activeCategory !== $category->slug,
                    ])
                >
                    <x-customer.category-icon :slug="$category->slug" class="size-4" />
                    {{ $category->name }}
                </button>
            @endforeach
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search the menu…" class="lg:w-64" />
    </div>

    @if ($this->items->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-16 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <flux:icon.magnifying-glass variant="outline" class="size-8 text-espresso-400 dark:text-cream-300" />
            <p class="text-espresso-600 dark:text-cream-300">No items match your search just yet.</p>
        </div>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->items as $item)
                <x-customer.menu-item-card :item="$item" wire:key="menu-item-{{ $item->id }}" />
            @endforeach
        </div>
    @endif
</div>
