<?php

use App\Models\Combo;
use App\Models\Offer;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.admin');
title('Offers & Combos — Admin — OpenCafe');

$offers = computed(fn () => Offer::query()->orderByDesc('created_at')->get());

$combos = computed(fn () => Combo::query()->with('menuItems')->orderBy('sort_order')->get());

?>

<div>
    <x-admin.page-header
        heading="Offers & Combos"
        subheading="OpenCafe has not defined any real promotional offers or combo deals yet."
    />

    <div class="flex flex-col gap-6">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:subheading class="mb-3">Offers ({{ $this->offers->count() }})</flux:subheading>

            @if ($this->offers->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                    No offers exist yet. This section will list real discount codes here once OpenCafe defines one — nothing is seeded or faked.
                </div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($this->offers as $offer)
                        <div wire:key="admin-offer-{{ $offer->id }}" class="rounded-xl border border-zinc-100 p-3 text-sm dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $offer->name }} ({{ $offer->code }})</span>
                                <flux:badge :color="$offer->is_active ? 'lime' : 'zinc'" size="sm">
                                    {{ $offer->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </div>
                            <p class="text-zinc-500 dark:text-zinc-400">
                                {{ $offer->type === \App\Enums\OfferType::Percentage ? $offer->value.'% off' : 'Flat '.$offer->value.' off' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:subheading class="mb-3">Combos ({{ $this->combos->count() }})</flux:subheading>

            @if ($this->combos->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                    No combo deals exist yet. This section will list real bundled items here once OpenCafe defines one — nothing is seeded or faked.
                </div>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($this->combos as $combo)
                        <div wire:key="admin-combo-{{ $combo->id }}" class="rounded-xl border border-zinc-100 p-3 text-sm dark:border-zinc-700">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ $combo->name }}</span>
                                <x-customer.price :amount="$combo->price" />
                            </div>
                            <p class="text-zinc-500 dark:text-zinc-400">{{ $combo->menuItems->pluck('name')->join(', ') }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
