<?php

use App\Enums\ReviewStatus;
use App\Enums\VariantSelectionType;
use App\Models\MenuItem;
use App\Models\Review;
use Flux\Flux;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.customer');

state(['menuItem' => null, 'selectedVariants' => [], 'quantity' => 1]);

mount(function (MenuItem $menuItem) {
    $menuItem->load([
        'category',
        'variantGroups' => fn ($query) => $query->orderBy('sort_order'),
        'variantGroups.variants' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order'),
    ]);

    $this->menuItem = $menuItem;
    title("{$menuItem->name} — OpenCafe");

    foreach ($menuItem->variantGroups as $group) {
        if ($group->selection_type === VariantSelectionType::Multiple) {
            $this->selectedVariants[$group->id] = [];
        } else {
            $this->selectedVariants[$group->id] = $group->variants->first()?->id;
        }
    }
});

$totalPrice = computed(function () {
    $total = (float) $this->menuItem->base_price;

    foreach ($this->menuItem->variantGroups as $group) {
        $selection = $this->selectedVariants[$group->id] ?? null;

        if (is_array($selection)) {
            foreach ($group->variants->whereIn('id', $selection) as $variant) {
                $total += (float) $variant->price_modifier;
            }
        } elseif ($selection) {
            $total += (float) ($group->variants->firstWhere('id', $selection)?->price_modifier ?? 0);
        }
    }

    return $total * $this->quantity;
});

$rating = computed(function () {
    $query = Review::query()
        ->where('menu_item_id', $this->menuItem->id)
        ->where('status', ReviewStatus::Approved)
        ->where('is_visible', true);

    return [
        'average' => round((clone $query)->avg('rating') ?? 0, 1),
        'count' => (clone $query)->count(),
    ];
});

$selectSingleVariant = function (int $groupId, int $variantId) {
    $this->selectedVariants[$groupId] = $variantId;
};

$toggleMultipleVariant = function (int $groupId, int $variantId) {
    $selected = $this->selectedVariants[$groupId] ?? [];

    $this->selectedVariants[$groupId] = in_array($variantId, $selected, true)
        ? array_values(array_diff($selected, [$variantId]))
        : [...$selected, $variantId];
};

$incrementQuantity = function () {
    $this->quantity++;
};

$decrementQuantity = function () {
    $this->quantity = max(1, $this->quantity - 1);
};

$addToCart = function () {
    if (! auth()->check()) {
        session()->flash('status', 'Please log in to add items to your cart.');

        return redirect()->guest(route('login'));
    }

    Flux::toast(
        heading: 'Added to cart',
        text: "{$this->quantity} × {$this->menuItem->name}",
        variant: 'success',
    );
};

?>

<div class="mx-auto max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
    <nav class="mb-8 flex flex-wrap items-center gap-2 text-sm text-espresso-500 dark:text-cream-300">
        <a href="{{ route('menu.index') }}" wire:navigate class="hover:text-accent-content">Menu</a>
        <span>/</span>
        @if ($menuItem->category)
            <a href="{{ route('menu.index', ['category' => $menuItem->category->slug]) }}" wire:navigate class="hover:text-accent-content">
                {{ $menuItem->category->name }}
            </a>
            <span>/</span>
        @endif
        <span class="text-espresso-800 dark:text-cream-50">{{ $menuItem->name }}</span>
    </nav>

    <div class="grid gap-12 lg:grid-cols-2 lg:items-start">
        {{-- Reserved for a future 3D / scroll-rotation food presentation --}}
        <div data-item-visual data-hero-stage class="relative mx-auto aspect-square w-full max-w-lg lg:sticky lg:top-24">
            <div class="absolute inset-0 rounded-[2.5rem] bg-linear-to-br from-orange-200 via-orange-100 to-espresso-200 opacity-70 blur-2xl dark:from-espresso-700 dark:via-espresso-800 dark:to-espresso-900"></div>

            <div class="absolute inset-0 flex items-center justify-center overflow-hidden rounded-[2.5rem] bg-linear-to-br from-orange-100 to-cream-50 shadow-xl ring-1 ring-espresso-900/5 dark:from-espresso-800 dark:to-espresso-900 dark:ring-cream-50/5">
                <x-customer.category-icon :slug="$menuItem->category?->slug" class="size-28 text-espresso-400/50 dark:text-cream-100/25" />
            </div>

            @if ($menuItem->is_featured)
                <flux:badge color="orange" class="absolute top-5 left-5 shadow-sm">Featured</flux:badge>
            @endif
        </div>

        <div class="flex flex-col gap-6">
            <div>
                @if ($this->rating['count'] > 0)
                    <div class="mb-2 flex items-center gap-2">
                        <x-customer.star-rating :rating="$this->rating['average']" />
                        <span class="text-sm text-espresso-500 dark:text-cream-300">
                            {{ $this->rating['average'] }} ({{ $this->rating['count'] }} review{{ $this->rating['count'] === 1 ? '' : 's' }})
                        </span>
                    </div>
                @endif

                <h1 class="text-3xl font-bold tracking-tight text-espresso-900 dark:text-cream-50">
                    {{ $menuItem->name }}
                </h1>

                @if ($menuItem->description)
                    <p class="mt-2 text-espresso-600 dark:text-cream-300">{{ $menuItem->description }}</p>
                @endif
            </div>

            @if ($menuItem->preparation_time_minutes)
                <div class="flex items-center gap-2 text-sm text-espresso-500 dark:text-cream-300">
                    <flux:icon.clock variant="micro" />
                    Ready in about {{ $menuItem->preparation_time_minutes }} minutes
                </div>
            @endif

            @foreach ($menuItem->variantGroups as $group)
                <div>
                    <flux:heading size="sm" class="mb-3">
                        {{ $group->name }}

                        @if ($group->is_required)
                            <span class="text-accent-content">*</span>
                        @endif
                    </flux:heading>

                    <div class="flex flex-wrap gap-2">
                        @foreach ($group->variants as $variant)
                            @php
                                $isMultiple = $group->selection_type === VariantSelectionType::Multiple;
                                $isSelected = $isMultiple
                                    ? in_array($variant->id, $selectedVariants[$group->id] ?? [], true)
                                    : ($selectedVariants[$group->id] ?? null) === $variant->id;
                            @endphp

                            <button
                                type="button"
                                wire:click="{{ $isMultiple ? 'toggleMultipleVariant' : 'selectSingleVariant' }}({{ $group->id }}, {{ $variant->id }})"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-sm font-medium transition',
                                    'border-transparent bg-(--color-accent) text-(--color-accent-foreground) shadow-sm' => $isSelected,
                                    'border-espresso-200 bg-white text-espresso-700 hover:border-orange-300 dark:border-espresso-700 dark:bg-espresso-800 dark:text-cream-200' => ! $isSelected,
                                ])
                            >
                                @if ($isSelected)
                                    <flux:icon.check variant="micro" class="size-3.5" />
                                @endif

                                {{ $variant->name }}

                                @if ($variant->price_modifier != 0)
                                    <span class="opacity-80">({{ $variant->price_modifier > 0 ? '+' : '' }}<x-customer.currency-symbol />{{ number_format($variant->price_modifier, 2) }})</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex flex-col gap-5 rounded-2xl border border-espresso-100 bg-cream-100 p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-espresso-700 dark:text-cream-200">Quantity</span>

                    <div class="flex items-center gap-3 rounded-xl border border-espresso-200 bg-white px-3 py-1 dark:border-espresso-700 dark:bg-espresso-900">
                        <button type="button" wire:click="decrementQuantity" class="flex size-6 items-center justify-center text-espresso-600 hover:text-accent-content dark:text-cream-200" aria-label="Decrease quantity">
                            <flux:icon.minus variant="micro" />
                        </button>
                        <span class="w-4 text-center font-medium">{{ $quantity }}</span>
                        <button type="button" wire:click="incrementQuantity" class="flex size-6 items-center justify-center text-espresso-600 hover:text-accent-content dark:text-cream-200" aria-label="Increase quantity">
                            <flux:icon.plus variant="micro" />
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4 border-t border-espresso-200 pt-5 dark:border-espresso-700">
                    <x-customer.price :amount="$this->totalPrice" class="text-2xl text-accent-content" />

                    <flux:button wire:click="addToCart" variant="primary" icon="shopping-bag">
                        Add to cart
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-16 border-t border-espresso-100 pt-12 dark:border-espresso-700">
        <flux:heading size="lg" class="mb-6">Customer reviews</flux:heading>

        <livewire:customer.review-list :menu-item="$menuItem" :limit="6" />
    </div>
</div>
