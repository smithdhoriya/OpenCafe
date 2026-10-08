<?php

use App\Models\Cart;
use App\Models\Combo;
use App\Models\Offer;
use Flux\Flux;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.customer');
title('Offers & Combos — OpenCafe');

$offers = computed(function () {
    return Offer::query()
        ->where('is_active', true)
        ->orderBy('created_at')
        ->get()
        ->filter(fn ($offer) => $offer->isCurrentlyActive())
        ->values();
});

$combos = computed(function () {
    return Combo::query()
        ->where('is_available', true)
        ->with(['comboItems.menuItem', 'comboItems.variant'])
        ->orderBy('sort_order')
        ->get()
        ->filter(fn ($combo) => $combo->isCurrentlyAvailable())
        ->values();
});

$addComboToCart = function (int $comboId) {
    if (! auth()->check()) {
        session()->flash('status', 'Please log in to add a combo to your cart.');

        return redirect()->guest(route('login'));
    }

    $combo = Combo::query()->whereKey($comboId)->first();

    if (! $combo || ! $combo->isCurrentlyAvailable()) {
        Flux::toast(
            heading: 'Unavailable',
            text: 'This combo is currently unavailable.',
            variant: 'danger',
        );

        return;
    }

    Cart::firstOrCreateForUser(auth()->user())->addCombo($combo, 1);

    $this->dispatch('cart-updated');

    Flux::toast(
        heading: 'Added to cart',
        text: $combo->name,
        variant: 'success',
    );
};

?>

<div class="mx-auto max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
    <x-customer.section-heading
        eyebrow="Save more"
        title="Offers & combos"
        description="Limited-time promo codes and bundled meals, priced to save you more."
        class="mb-12"
    />

    @if ($this->offers->isNotEmpty())
        <div class="mb-16">
            <flux:heading size="lg" class="mb-6">Promo codes</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->offers as $offer)
                    <x-customer.offer-card :offer="$offer" />
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->combos->isNotEmpty())
        <div>
            <flux:heading size="lg" class="mb-6">Combos</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->combos as $combo)
                    <x-customer.combo-card :combo="$combo" />
                @endforeach
            </div>
        </div>
    @endif

    @if ($this->offers->isEmpty() && $this->combos->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-16 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <flux:icon.tag variant="outline" class="size-8 text-espresso-400 dark:text-cream-300" />
            <p class="text-espresso-600 dark:text-cream-300">No active offers or combos right now — check back soon.</p>
        </div>
    @endif
</div>
