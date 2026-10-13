<?php

use App\Enums\OfferType;
use App\Models\Combo;
use App\Models\MenuItem;
use App\Models\Offer;
use Flux\Flux;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{computed, layout, state, title, updated, usesFileUploads, usesPagination};

usesPagination();
usesFileUploads();

layout('components.layouts.admin');
title('Offers & Combos — Admin — OpenCafe');

state([
    'activeTab' => 'offers',

    'offerSearch' => '',
    'offerStatusFilter' => '',

    'comboSearch' => '',
    'comboStatusFilter' => '',

    // Offer form
    'showOfferForm' => false,
    'editingOfferId' => null,
    'offer_code' => '',
    'offer_name' => '',
    'offer_description' => '',
    'offer_type' => 'percentage',
    'offer_value' => '',
    'offer_min_order_total' => '',
    'offer_max_discount_amount' => '',
    'offer_starts_at' => '',
    'offer_ends_at' => '',
    'offer_usage_limit' => '',
    'offer_usage_limit_per_user' => '',
    'offer_is_active' => true,

    // Combo form
    'showComboForm' => false,
    'editingComboId' => null,
    'combo_name' => '',
    'combo_slug' => '',
    'combo_description' => '',
    'combo_price' => '',
    'combo_compare_at_price' => '',
    'combo_image' => null,
    'combo_current_image_path' => null,
    'combo_is_available' => true,
    'combo_starts_at' => '',
    'combo_ends_at' => '',
    'combo_sort_order' => 0,
    'comboItemMenuItemId' => '',
    'comboItemQuantity' => 1,
    'comboItems' => [],
]);

updated([
    'offerSearch' => fn () => $this->resetPage('offers-page'),
    'offerStatusFilter' => fn () => $this->resetPage('offers-page'),
    'comboSearch' => fn () => $this->resetPage('combos-page'),
    'comboStatusFilter' => fn () => $this->resetPage('combos-page'),
]);

// Mirrors Offer::isCurrentlyActive() / Combo::isCurrentlyAvailable() as SQL
// conditions instead of loading every row into PHP just to count a few —
// both resolve to: flag is on, and now falls inside the optional start/end
// window (null bounds meaning that side is unrestricted).
$activeOffersCount = computed(fn () => Offer::query()
    ->where('is_active', true)
    ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
    ->count());

$activeCombosCount = computed(fn () => Combo::query()
    ->where('is_available', true)
    ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
    ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
    ->count());

$menuItemOptions = computed(fn () => MenuItem::query()->orderBy('name')->get());

$offers = computed(function () {
    $term = '%'.trim($this->offerSearch).'%';

    return Offer::query()
        ->when(trim($this->offerSearch) !== '', function ($query) use ($term) {
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', $term)->orWhere('code', 'like', $term);
            });
        })
        ->when($this->offerStatusFilter === 'active', fn ($query) => $query->where('is_active', true))
        ->when($this->offerStatusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
        ->orderByDesc('created_at')
        ->paginate(10, pageName: 'offers-page');
});

$combos = computed(function () {
    $term = '%'.trim($this->comboSearch).'%';

    return Combo::query()
        ->with('menuItems')
        ->when(trim($this->comboSearch) !== '', fn ($query) => $query->where('name', 'like', $term))
        ->when($this->comboStatusFilter === 'available', fn ($query) => $query->where('is_available', true))
        ->when($this->comboStatusFilter === 'unavailable', fn ($query) => $query->where('is_available', false))
        ->orderBy('sort_order')
        ->paginate(10, pageName: 'combos-page');
});

// ----- Offers -----

$resetOfferForm = function () {
    $this->editingOfferId = null;
    $this->offer_code = '';
    $this->offer_name = '';
    $this->offer_description = '';
    $this->offer_type = 'percentage';
    $this->offer_value = '';
    $this->offer_min_order_total = '';
    $this->offer_max_discount_amount = '';
    $this->offer_starts_at = '';
    $this->offer_ends_at = '';
    $this->offer_usage_limit = '';
    $this->offer_usage_limit_per_user = '';
    $this->offer_is_active = true;
    $this->resetErrorBag();
};

$startCreateOffer = function () {
    $this->resetOfferForm();
    $this->showOfferForm = true;
};

$startEditOffer = function (int $offerId) {
    $offer = Offer::query()->whereKey($offerId)->first();

    if (! $offer) {
        return;
    }

    $this->editingOfferId = $offer->id;
    $this->offer_code = $offer->code;
    $this->offer_name = $offer->name;
    $this->offer_description = $offer->description ?? '';
    $this->offer_type = $offer->type->value;
    $this->offer_value = (string) $offer->value;
    $this->offer_min_order_total = $offer->min_order_total !== null ? (string) $offer->min_order_total : '';
    $this->offer_max_discount_amount = $offer->max_discount_amount !== null ? (string) $offer->max_discount_amount : '';
    $this->offer_starts_at = $offer->starts_at?->format('Y-m-d\TH:i') ?? '';
    $this->offer_ends_at = $offer->ends_at?->format('Y-m-d\TH:i') ?? '';
    $this->offer_usage_limit = $offer->usage_limit !== null ? (string) $offer->usage_limit : '';
    $this->offer_usage_limit_per_user = $offer->usage_limit_per_user !== null ? (string) $offer->usage_limit_per_user : '';
    $this->offer_is_active = $offer->is_active;

    $this->showOfferForm = true;
};

$saveOffer = function () {
    // Normalized before validation so the uniqueness check and the stored
    // value always agree, regardless of the database's collation.
    $this->offer_code = Str::upper(trim($this->offer_code));

    $validated = $this->validate([
        'offer_code' => [
            'required', 'string', 'max:50', 'alpha_dash',
            Rule::unique('offers', 'code')->ignore($this->editingOfferId),
        ],
        'offer_name' => ['required', 'string', 'max:150'],
        'offer_description' => ['nullable', 'string'],
        'offer_type' => ['required', 'in:percentage,fixed_amount'],
        'offer_value' => [
            'required', 'numeric', 'min:0.01',
            $this->offer_type === 'percentage' ? 'max:100' : 'max:99999999.99',
        ],
        'offer_min_order_total' => ['nullable', 'numeric', 'min:0'],
        'offer_max_discount_amount' => ['nullable', 'numeric', 'min:0'],
        'offer_starts_at' => ['nullable', 'date'],
        'offer_ends_at' => ['nullable', 'date'],
        'offer_usage_limit' => ['nullable', 'integer', 'min:1'],
        'offer_usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
        'offer_is_active' => ['boolean'],
    ]);

    if (filled($validated['offer_starts_at']) && filled($validated['offer_ends_at']) && $validated['offer_ends_at'] <= $validated['offer_starts_at']) {
        $this->addError('offer_ends_at', 'The end date must be after the start date.');

        return;
    }

    $payload = [
        'code' => $validated['offer_code'],
        'name' => $validated['offer_name'],
        'description' => filled($validated['offer_description']) ? $validated['offer_description'] : null,
        'type' => $validated['offer_type'],
        'value' => $validated['offer_value'],
        'min_order_total' => filled($validated['offer_min_order_total']) ? $validated['offer_min_order_total'] : null,
        'max_discount_amount' => filled($validated['offer_max_discount_amount']) ? $validated['offer_max_discount_amount'] : null,
        'starts_at' => filled($validated['offer_starts_at']) ? $validated['offer_starts_at'] : null,
        'ends_at' => filled($validated['offer_ends_at']) ? $validated['offer_ends_at'] : null,
        'usage_limit' => filled($validated['offer_usage_limit']) ? $validated['offer_usage_limit'] : null,
        'usage_limit_per_user' => filled($validated['offer_usage_limit_per_user']) ? $validated['offer_usage_limit_per_user'] : null,
        'is_active' => $validated['offer_is_active'],
    ];

    if ($this->editingOfferId) {
        $offer = Offer::query()->whereKey($this->editingOfferId)->first();

        if (! $offer) {
            $this->showOfferForm = false;
            $this->resetOfferForm();

            return;
        }

        $offer->update($payload);

        Flux::toast(text: 'Offer updated.', heading: 'Saved', variant: 'success');
    } else {
        Offer::create($payload + ['times_used' => 0]);

        Flux::toast(text: 'Offer created.', heading: 'Saved', variant: 'success');
    }

    $this->showOfferForm = false;
    $this->resetOfferForm();
};

$toggleOfferActive = function (int $offerId) {
    $offer = Offer::query()->whereKey($offerId)->first();

    $offer?->update(['is_active' => ! $offer->is_active]);
};

$deleteOffer = function (int $offerId) {
    Offer::query()->whereKey($offerId)->first()?->delete();

    Flux::toast(text: 'Offer removed.', heading: 'Deleted', variant: 'success');
};

// ----- Combos -----

$resetComboForm = function () {
    $this->editingComboId = null;
    $this->combo_name = '';
    $this->combo_slug = '';
    $this->combo_description = '';
    $this->combo_price = '';
    $this->combo_compare_at_price = '';
    $this->combo_image = null;
    $this->combo_current_image_path = null;
    $this->combo_is_available = true;
    $this->combo_starts_at = '';
    $this->combo_ends_at = '';
    $this->combo_sort_order = 0;
    $this->comboItemMenuItemId = '';
    $this->comboItemQuantity = 1;
    $this->comboItems = [];
    $this->resetErrorBag();
};

$generateComboSlug = function () {
    $this->combo_slug = Str::slug($this->combo_name);
};

$startCreateCombo = function () {
    $this->resetComboForm();
    $this->showComboForm = true;
};

$startEditCombo = function (int $comboId) {
    $combo = Combo::query()->with('comboItems.menuItem')->whereKey($comboId)->first();

    if (! $combo) {
        return;
    }

    $this->editingComboId = $combo->id;
    $this->combo_name = $combo->name;
    $this->combo_slug = $combo->slug;
    $this->combo_description = $combo->description ?? '';
    $this->combo_price = (string) $combo->price;
    $this->combo_compare_at_price = $combo->compare_at_price !== null ? (string) $combo->compare_at_price : '';
    $this->combo_image = null;
    $this->combo_current_image_path = $combo->image_path;
    $this->combo_is_available = $combo->is_available;
    $this->combo_starts_at = $combo->starts_at?->format('Y-m-d\TH:i') ?? '';
    $this->combo_ends_at = $combo->ends_at?->format('Y-m-d\TH:i') ?? '';
    $this->combo_sort_order = $combo->sort_order;

    $this->comboItems = $combo->comboItems->map(fn ($comboItem) => [
        'menu_item_id' => $comboItem->menu_item_id,
        'name' => $comboItem->menuItem?->name ?? 'Deleted item',
        'quantity' => $comboItem->quantity,
    ])->values()->all();

    $this->showComboForm = true;
};

$addComboItem = function () {
    if ($this->comboItemMenuItemId === '') {
        return;
    }

    $menuItem = MenuItem::query()->whereKey($this->comboItemMenuItemId)->first();

    if (! $menuItem) {
        return;
    }

    $quantity = max(1, (int) $this->comboItemQuantity);

    foreach ($this->comboItems as $index => $row) {
        if ($row['menu_item_id'] === $menuItem->id) {
            $this->comboItems[$index]['quantity'] += $quantity;
            $this->comboItemMenuItemId = '';
            $this->comboItemQuantity = 1;

            return;
        }
    }

    $this->comboItems[] = [
        'menu_item_id' => $menuItem->id,
        'name' => $menuItem->name,
        'quantity' => $quantity,
    ];

    $this->comboItemMenuItemId = '';
    $this->comboItemQuantity = 1;
};

$removeComboItem = function (int $index) {
    unset($this->comboItems[$index]);
    $this->comboItems = array_values($this->comboItems);
};

$saveCombo = function () {
    $validated = $this->validate([
        'combo_name' => ['required', 'string', 'max:150'],
        'combo_slug' => [
            'required', 'string', 'max:170', 'alpha_dash',
            Rule::unique('combos', 'slug')->ignore($this->editingComboId),
        ],
        'combo_description' => ['nullable', 'string'],
        'combo_price' => ['required', 'numeric', 'min:0'],
        'combo_compare_at_price' => ['nullable', 'numeric', 'min:0'],
        'combo_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        'combo_is_available' => ['boolean'],
        'combo_starts_at' => ['nullable', 'date'],
        'combo_ends_at' => ['nullable', 'date'],
        'combo_sort_order' => ['integer', 'min:0'],
    ]);

    if (filled($validated['combo_starts_at']) && filled($validated['combo_ends_at']) && $validated['combo_ends_at'] <= $validated['combo_starts_at']) {
        $this->addError('combo_ends_at', 'The end date must be after the start date.');

        return;
    }

    if (count($this->comboItems) < 1) {
        $this->addError('comboItems', 'Add at least one menu item to this combo.');

        return;
    }

    // Every item was added from the live menu-item list, but re-verify it
    // still exists before persisting — nothing invalid can silently enter a combo.
    $menuItemIds = collect($this->comboItems)->pluck('menu_item_id')->unique();
    $validMenuItemIds = MenuItem::query()->whereIn('id', $menuItemIds)->pluck('id');

    if ($validMenuItemIds->count() !== $menuItemIds->count()) {
        $this->addError('comboItems', 'One of the selected menu items no longer exists.');

        return;
    }

    $payload = [
        'name' => $validated['combo_name'],
        'slug' => $validated['combo_slug'],
        'description' => filled($validated['combo_description']) ? $validated['combo_description'] : null,
        'price' => $validated['combo_price'],
        'compare_at_price' => filled($validated['combo_compare_at_price']) ? $validated['combo_compare_at_price'] : null,
        'is_available' => $validated['combo_is_available'],
        'starts_at' => filled($validated['combo_starts_at']) ? $validated['combo_starts_at'] : null,
        'ends_at' => filled($validated['combo_ends_at']) ? $validated['combo_ends_at'] : null,
        'sort_order' => $validated['combo_sort_order'],
    ];

    if ($this->combo_image) {
        $filename = Str::slug($validated['combo_name']).'-'.Str::random(6).'.'.$this->combo_image->getClientOriginalExtension();
        File::ensureDirectoryExists(public_path('images/combos'));
        File::copy($this->combo_image->getRealPath(), public_path('images/combos/'.$filename));
        $payload['image_path'] = 'images/combos/'.$filename;
    }

    if ($this->editingComboId) {
        $combo = Combo::query()->whereKey($this->editingComboId)->first();

        if (! $combo) {
            $this->showComboForm = false;
            $this->resetComboForm();

            return;
        }

        $combo->update($payload);
    } else {
        $combo = Combo::create($payload);
    }

    $combo->menuItems()->sync(
        collect($this->comboItems)->mapWithKeys(fn ($row) => [
            $row['menu_item_id'] => ['menu_item_variant_id' => null, 'quantity' => max(1, (int) $row['quantity'])],
        ])->all()
    );

    Flux::toast(text: $this->editingComboId ? 'Combo updated.' : 'Combo created.', heading: 'Saved', variant: 'success');

    $this->showComboForm = false;
    $this->resetComboForm();
};

$toggleComboAvailable = function (int $comboId) {
    $combo = Combo::query()->whereKey($comboId)->first();

    $combo?->update(['is_available' => ! $combo->is_available]);
};

$deleteCombo = function (int $comboId) {
    Combo::query()->whereKey($comboId)->first()?->delete();

    Flux::toast(text: 'Combo removed.', heading: 'Deleted', variant: 'success');
};

?>

<div>
    <x-admin.page-header heading="Offers & Combos" subheading="Promo codes and bundled deals for OpenCafe." />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/40 dark:bg-zinc-900">
            <flux:subheading>Active Offers</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ $this->activeOffersCount }}</flux:heading>
        </div>

        <div class="rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/40 dark:bg-zinc-900">
            <flux:subheading>Active Combos</flux:subheading>
            <flux:heading size="xl" class="mt-1">{{ $this->activeCombosCount }}</flux:heading>
        </div>
    </div>

    <div class="mb-6 flex gap-2 border-b border-emerald-100 dark:border-emerald-900/40">
        <button
            type="button"
            wire:click="$set('activeTab', 'offers')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $activeTab === 'offers' ? 'border-emerald-600 text-emerald-700 dark:border-emerald-500 dark:text-emerald-400' : 'border-transparent text-zinc-500 dark:text-zinc-400' }}"
        >
            Offers
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'combos')"
            class="border-b-2 px-4 py-2 text-sm font-medium {{ $activeTab === 'combos' ? 'border-emerald-600 text-emerald-700 dark:border-emerald-500 dark:text-emerald-400' : 'border-transparent text-zinc-500 dark:text-zinc-400' }}"
        >
            Combos
        </button>
    </div>

    @if ($activeTab === 'offers')
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <flux:input wire:model.live.debounce.400ms="offerSearch" placeholder="Search by name or code..." icon="magnifying-glass" />

                <flux:select wire:model.live="offerStatusFilter" class="max-w-[10rem]">
                    <flux:select.option value="">All statuses</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                </flux:select>
            </div>

            <flux:button wire:click="startCreateOffer" variant="primary" icon="plus">Add offer</flux:button>
        </div>

        @if ($this->offers->isEmpty())
            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-16 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                No offers match your filters.
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-emerald-100 dark:border-emerald-900/40">
                <table class="w-full min-w-[760px] divide-y divide-emerald-100 text-left text-sm dark:divide-emerald-900/40">
                    <thead class="bg-emerald-50/60 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Offer</th>
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Discount</th>
                            <th class="px-4 py-3 font-medium text-right">Used</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-emerald-100 bg-white dark:divide-emerald-900/40 dark:bg-zinc-900">
                        @foreach ($this->offers as $offer)
                            <tr wire:key="admin-offer-{{ $offer->id }}" class="transition hover:bg-emerald-50/40 dark:hover:bg-emerald-900/10">
                                <td class="px-4 py-3 font-medium">{{ $offer->name }}</td>
                                <td class="px-4 py-3"><flux:badge color="orange" size="sm" class="font-mono">{{ $offer->code }}</flux:badge></td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                                    {{ $offer->type === \App\Enums\OfferType::Percentage ? rtrim(rtrim((string) $offer->value, '0'), '.').'% off' : 'Flat '.number_format((float) $offer->value, 2).' off' }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ $offer->times_used }}{{ $offer->usage_limit ? ' / '.$offer->usage_limit : '' }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge :color="$offer->isCurrentlyActive() ? 'lime' : 'zinc'" size="sm">
                                        {{ $offer->isCurrentlyActive() ? 'Active' : ($offer->is_active ? 'Scheduled/Expired' : 'Inactive') }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <flux:button wire:click="toggleOfferActive({{ $offer->id }})" variant="ghost" size="sm">
                                        {{ $offer->is_active ? 'Deactivate' : 'Activate' }}
                                    </flux:button>
                                    <flux:button wire:click="startEditOffer({{ $offer->id }})" variant="ghost" size="sm" icon="pencil" square aria-label="Edit offer" />
                                    <flux:button
                                        wire:click="deleteOffer({{ $offer->id }})"
                                        wire:confirm="Delete this offer? This cannot be undone."
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        square
                                        aria-label="Delete offer"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->offers->links() }}</div>
        @endif
    @else
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <flux:input wire:model.live.debounce.400ms="comboSearch" placeholder="Search by name..." icon="magnifying-glass" />

                <flux:select wire:model.live="comboStatusFilter" class="max-w-[10rem]">
                    <flux:select.option value="">All statuses</flux:select.option>
                    <flux:select.option value="available">Available</flux:select.option>
                    <flux:select.option value="unavailable">Unavailable</flux:select.option>
                </flux:select>
            </div>

            <flux:button wire:click="startCreateCombo" variant="primary" icon="plus">Add combo</flux:button>
        </div>

        @if ($this->combos->isEmpty())
            <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-16 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                No combos match your filters.
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->combos as $combo)
                    <div wire:key="admin-combo-{{ $combo->id }}" class="flex flex-col gap-3 rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/40 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-2">
                            <flux:heading size="sm">{{ $combo->name }}</flux:heading>
                            <flux:badge :color="$combo->isCurrentlyAvailable() ? 'lime' : 'zinc'" size="sm">
                                {{ $combo->isCurrentlyAvailable() ? 'Available' : 'Unavailable' }}
                            </flux:badge>
                        </div>

                        <x-customer.price :amount="$combo->price" />

                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $combo->menuItems->pluck('name')->join(', ') }}
                        </p>

                        <div class="mt-auto flex justify-end gap-2">
                            <flux:button wire:click="toggleComboAvailable({{ $combo->id }})" variant="ghost" size="sm">
                                {{ $combo->is_available ? 'Deactivate' : 'Activate' }}
                            </flux:button>
                            <flux:button wire:click="startEditCombo({{ $combo->id }})" variant="ghost" size="sm" icon="pencil" square aria-label="Edit combo" />
                            <flux:button
                                wire:click="deleteCombo({{ $combo->id }})"
                                wire:confirm="Delete this combo? This cannot be undone."
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                square
                                aria-label="Delete combo"
                            />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $this->combos->links() }}</div>
        @endif
    @endif

    {{-- Offer form modal --}}
    <flux:modal wire:model.self="showOfferForm" class="w-full max-w-xl" @close="resetOfferForm">
        <form wire:submit="saveOffer" class="space-y-6">
            <flux:heading size="lg">{{ $editingOfferId ? 'Edit offer' : 'Add offer' }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="offer_code" label="Code" required placeholder="SAVE10" class="sm:col-span-2" />
                <flux:input wire:model="offer_name" label="Name" required class="sm:col-span-2" />
                <flux:textarea wire:model="offer_description" label="Description (optional)" rows="2" class="sm:col-span-2" />

                <flux:select wire:model="offer_type" label="Discount type">
                    <flux:select.option value="percentage">Percentage</flux:select.option>
                    <flux:select.option value="fixed_amount">Fixed amount</flux:select.option>
                </flux:select>

                <flux:input wire:model="offer_value" label="Discount value" type="number" step="0.01" min="0" required />

                <flux:input wire:model="offer_min_order_total" label="Minimum order (optional)" type="number" step="0.01" min="0" />
                <flux:input wire:model="offer_max_discount_amount" label="Max discount amount (optional)" type="number" step="0.01" min="0" />

                <flux:input wire:model="offer_starts_at" label="Starts at (optional)" type="datetime-local" />
                <flux:input wire:model="offer_ends_at" label="Ends at (optional)" type="datetime-local" />

                <flux:input wire:model="offer_usage_limit" label="Total usage limit (optional)" type="number" min="1" />
                <flux:input wire:model="offer_usage_limit_per_user" label="Per-customer limit (optional)" type="number" min="1" />

                <flux:checkbox wire:model="offer_is_active" label="Active" class="sm:col-span-2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showOfferForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save offer</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Combo form modal --}}
    <flux:modal wire:model.self="showComboForm" class="w-full max-w-2xl" @close="resetComboForm">
        <form wire:submit="saveCombo" class="space-y-6">
            <flux:heading size="lg">{{ $editingComboId ? 'Edit combo' : 'Add combo' }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="combo_name" label="Name" required class="sm:col-span-2" />

                <div class="flex items-end gap-2 sm:col-span-2">
                    <flux:input wire:model="combo_slug" label="Slug" required class="flex-1" />
                    <flux:button type="button" wire:click="generateComboSlug" variant="ghost">From name</flux:button>
                </div>

                <flux:textarea wire:model="combo_description" label="Description (optional)" rows="2" class="sm:col-span-2" />

                <flux:input wire:model="combo_price" label="Combo price" type="number" step="0.01" min="0" required />
                <flux:input wire:model="combo_compare_at_price" label="Compare-at price (optional)" type="number" step="0.01" min="0" />

                <div class="sm:col-span-2">
                    <flux:input wire:model="combo_image" type="file" label="Photo (optional)" accept="image/*" />

                    @if ($combo_current_image_path)
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Current file: {{ $combo_current_image_path }}</p>
                    @endif
                </div>

                <flux:input wire:model="combo_starts_at" label="Starts at (optional)" type="datetime-local" />
                <flux:input wire:model="combo_ends_at" label="Ends at (optional)" type="datetime-local" />
                <flux:input wire:model="combo_sort_order" label="Sort order" type="number" min="0" />
                <flux:checkbox wire:model="combo_is_available" label="Available to customers" />
            </div>

            <div>
                <flux:subheading class="mb-2">Combo items</flux:subheading>

                @error('comboItems')
                    <p class="mb-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-col gap-2">
                    @forelse ($comboItems as $index => $row)
                        <div wire:key="combo-item-row-{{ $index }}" class="flex items-center justify-between gap-3 rounded-lg border border-emerald-100 px-3 py-2 text-sm dark:border-emerald-900/40">
                            <span>{{ $row['quantity'] }} &times; {{ $row['name'] }}</span>
                            <flux:button type="button" wire:click="removeComboItem({{ $index }})" variant="ghost" size="sm" icon="x-mark" square aria-label="Remove item" />
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No items added yet.</p>
                    @endforelse
                </div>

                <div class="mt-3 flex items-end gap-2">
                    <flux:select wire:model="comboItemMenuItemId" label="Menu item" class="flex-1">
                        <flux:select.option value="">Select a menu item...</flux:select.option>
                        @foreach ($this->menuItemOptions as $menuItem)
                            <flux:select.option value="{{ $menuItem->id }}">{{ $menuItem->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="comboItemQuantity" type="number" min="1" label="Qty" class="w-20" />

                    <flux:button type="button" wire:click="addComboItem" variant="ghost">Add</flux:button>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showComboForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save combo</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
