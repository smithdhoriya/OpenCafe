<?php

use App\Models\Combo;
use App\Models\Offer;
use function Livewire\Volt\{layout, state, title};

layout('components.layouts.customer');
title('Offers & Combos — OpenCafe');

state([
    'offers' => fn () => Offer::query()->where('is_active', true)->orderBy('created_at')->get(),
    'combos' => fn () => Combo::query()
        ->where('is_available', true)
        ->with(['comboItems.menuItem', 'comboItems.variant'])
        ->orderBy('sort_order')
        ->get(),
]);

?>

<div class="mx-auto max-w-7xl px-6 py-16 lg:px-8 lg:py-20">
    <x-customer.section-heading
        eyebrow="Save more"
        title="Offers & combos"
        description="Limited-time promo codes and bundled meals, priced to save you more."
        class="mb-12"
    />

    @if ($offers->isNotEmpty())
        <div class="mb-16">
            <flux:heading size="lg" class="mb-6">Promo codes</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offers as $offer)
                    <x-customer.offer-card :offer="$offer" />
                @endforeach
            </div>
        </div>
    @endif

    @if ($combos->isNotEmpty())
        <div>
            <flux:heading size="lg" class="mb-6">Combos</flux:heading>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($combos as $combo)
                    <x-customer.combo-card :combo="$combo" />
                @endforeach
            </div>
        </div>
    @endif

    @if ($offers->isEmpty() && $combos->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-16 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <flux:icon.tag variant="outline" class="size-8 text-espresso-400 dark:text-cream-300" />
            <p class="text-espresso-600 dark:text-cream-300">No active offers or combos right now — check back soon.</p>
        </div>
    @endif
</div>
