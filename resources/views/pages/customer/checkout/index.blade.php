<?php

use App\Actions\Orders\CreateOrderFromCart;
use App\Enums\OrderType;
use App\Models\CafeSetting;
use App\Models\Cart;
use App\Models\DeliveryRate;
use App\Models\Offer;
use App\Support\Geo;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{computed, layout, mount, on, state, title};

layout('components.layouts.customer');
title('Checkout — OpenCafe');

state([
    'fulfillmentType' => OrderType::Takeaway->value,
    'selectedAddressId' => null,
    'offerCode' => '',
    'placingOrder' => false,
]);

mount(function () {
    $cart = Cart::activeForUser(auth()->user());

    if (! $cart || $cart->items()->doesntExist()) {
        session()->flash('status', 'Your cart is empty — add something tasty before checking out.');

        $this->redirect(route('cart.index'), navigate: true);

        return;
    }

    $defaultAddress = auth()->user()->customerAddresses()->where('is_default', true)->first();

    if ($defaultAddress) {
        $this->selectedAddressId = $defaultAddress->id;
    }
});

on(['cart-updated' => fn () => null]);

$cart = computed(fn () => Cart::activeForUser(auth()->user()));

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

// Preview only — the actual discount is always recalculated and frozen
// server-side inside CreateOrderFromCart when the order is actually placed.
$appliedOffer = computed(function () {
    $code = trim($this->offerCode);

    if ($code === '') {
        return null;
    }

    $offer = Offer::query()->where('code', Str::upper($code))->first();

    if (! $offer || ! $offer->isCurrentlyActive()) {
        return null;
    }

    if (! $offer->meetsMinimumOrder($this->subtotal)) {
        return null;
    }

    if (! $offer->hasUsesRemaining() || ! $offer->hasUsesRemainingForUser(auth()->user())) {
        return null;
    }

    return $offer;
});

$offerCodeIsInvalid = computed(fn () => trim($this->offerCode) !== '' && $this->appliedOffer === null);

$discount = computed(fn () => $this->appliedOffer?->calculateDiscount($this->subtotal) ?? 0.0);

$addresses = computed(function () {
    return auth()->user()->customerAddresses()->orderByDesc('is_default')->orderByDesc('created_at')->get();
});

// Scoped to the authenticated user: a tampered selectedAddressId never resolves
// to another customer's address.
$selectedAddress = computed(function () {
    if (! $this->selectedAddressId) {
        return null;
    }

    return auth()->user()->customerAddresses()->whereKey($this->selectedAddressId)->first();
});

$deliveryEnabled = computed(fn () => (bool) CafeSetting::get('delivery_enabled', false));

$minOrderForDelivery = computed(fn () => (float) CafeSetting::get('min_order_for_delivery', 0));

$meetsMinimumForDelivery = computed(fn () => $this->subtotal >= $this->minOrderForDelivery);

$cafeCoordinates = computed(function () {
    $latitude = CafeSetting::get('cafe_latitude');
    $longitude = CafeSetting::get('cafe_longitude');

    if ($latitude === null || $longitude === null) {
        return null;
    }

    return ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
});

$deliveryDistanceKm = computed(function () {
    $address = $this->selectedAddress;
    $cafe = $this->cafeCoordinates;

    if (! $address || ! $cafe || $address->latitude === null || $address->longitude === null) {
        return null;
    }

    return Geo::distanceKm(
        (float) $address->latitude,
        (float) $address->longitude,
        $cafe['latitude'],
        $cafe['longitude'],
    );
});

$deliveryRate = computed(function () {
    $distance = $this->deliveryDistanceKm;

    return $distance === null ? null : DeliveryRate::forDistance($distance);
});

// Whether delivery is possible right now, independent of which fulfillment
// type is currently selected.
$deliveryAvailable = computed(function () {
    return $this->deliveryEnabled
        && $this->meetsMinimumForDelivery
        && $this->selectedAddress !== null
        && $this->deliveryRate !== null;
});

$deliveryCharge = computed(function () {
    if ($this->fulfillmentType !== OrderType::Delivery->value) {
        return 0.0;
    }

    return $this->deliveryAvailable ? (float) $this->deliveryRate->charge : 0.0;
});

$total = computed(fn () => $this->subtotal - $this->discount + $this->deliveryCharge);

$canCheckout = computed(function () {
    if ($this->fulfillmentType === OrderType::Takeaway->value) {
        return true;
    }

    return $this->deliveryAvailable;
});

// The actual pricing/eligibility are always recalculated inside the action from
// fresh server state — the client only ever supplies which fulfillment type and
// which (user-owned) address id were chosen.
$placeOrder = function (CreateOrderFromCart $createOrderFromCart) {
    if ($this->placingOrder) {
        return;
    }

    $this->placingOrder = true;

    try {
        $order = $createOrderFromCart->handle(
            auth()->user(),
            OrderType::tryFrom($this->fulfillmentType) ?? OrderType::Takeaway,
            $this->selectedAddressId !== null ? (int) $this->selectedAddressId : null,
            trim($this->offerCode) !== '' ? $this->offerCode : null,
        );
    } catch (ValidationException $e) {
        $this->placingOrder = false;

        Flux::toast(
            text: $e->validator->errors()->first(),
            heading: 'Unable to place order',
            variant: 'danger',
        );

        return;
    }

    $this->redirect(route('orders.show', $order), navigate: true);
};

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
    <flux:heading size="xl" class="mb-8">Checkout</flux:heading>

    <div class="flex flex-col gap-6">
        <div class="rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm" class="mb-4">Order summary</flux:heading>

            <div class="flex flex-col gap-3">
                @foreach ($this->items as $item)
                    <div wire:key="checkout-item-{{ $item->id }}" class="flex items-center justify-between gap-4 text-sm">
                        <div class="flex flex-col">
                            <span class="font-medium text-espresso-800 dark:text-cream-100">
                                {{ $item->quantity }} &times; {{ $item->isCombo() ? $item->combo->name : $item->menuItem->name }}
                            </span>

                            @if ($item->isCombo())
                                <span class="text-espresso-500 dark:text-cream-300">Combo</span>
                            @elseif ($item->variant)
                                <span class="text-espresso-500 dark:text-cream-300">{{ $item->variant->name }}</span>
                            @endif
                        </div>

                        <x-customer.price :amount="$item->line_total" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm" class="mb-4">How would you like it?</flux:heading>

            <flux:radio.group wire:model.live="fulfillmentType" variant="buttons" class="w-full *:flex-1">
                <flux:radio value="takeaway" label="Takeaway" />
                <flux:radio value="delivery" label="Delivery" :disabled="! $this->deliveryEnabled || ! $this->meetsMinimumForDelivery" />
            </flux:radio.group>

            @if (! $this->deliveryEnabled)
                <p class="mt-3 text-sm text-espresso-500 dark:text-cream-300">Delivery is currently unavailable.</p>
            @elseif (! $this->meetsMinimumForDelivery)
                <p class="mt-3 text-sm text-espresso-500 dark:text-cream-300">
                    Add <x-customer.price :amount="$this->minOrderForDelivery - $this->subtotal" /> more to your order to unlock delivery (minimum <x-customer.price :amount="$this->minOrderForDelivery" />).
                </p>
            @endif

            @if ($fulfillmentType === 'delivery')
                <div class="mt-5 flex flex-col gap-3 border-t border-espresso-100 pt-5 dark:border-espresso-700">
                    @if ($this->addresses->isEmpty())
                        <p class="text-sm text-espresso-600 dark:text-cream-300">
                            You don't have a saved delivery address yet.
                            <a href="{{ route('addresses.index') }}" wire:navigate class="font-medium text-accent-content underline">Add one</a>
                        </p>
                    @else
                        @foreach ($this->addresses as $address)
                            <label
                                wire:key="checkout-address-{{ $address->id }}"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-sm transition {{ (int) $selectedAddressId === $address->id ? 'border-orange-400 bg-orange-50 dark:bg-espresso-700/60' : 'border-espresso-200 dark:border-espresso-700' }}"
                            >
                                <input type="radio" wire:model.live="selectedAddressId" value="{{ $address->id }}" class="mt-1" />

                                <span class="flex flex-col">
                                    <span class="flex items-center gap-2 font-medium text-espresso-800 dark:text-cream-100">
                                        {{ $address->label ?: 'Address' }}

                                        @if ($address->is_default)
                                            <flux:badge color="orange" size="sm">Default</flux:badge>
                                        @endif
                                    </span>

                                    <span class="text-espresso-500 dark:text-cream-300">
                                        {{ $address->address_line_1 }}, {{ $address->city }}
                                    </span>
                                </span>
                            </label>
                        @endforeach

                        @if ($this->selectedAddress && $this->deliveryDistanceKm !== null)
                            @if ($this->deliveryRate)
                                <p class="text-sm text-espresso-600 dark:text-cream-300">
                                    {{ number_format($this->deliveryDistanceKm, 1) }} km away &middot; delivery charge <x-customer.price :amount="$this->deliveryRate->charge" />
                                </p>
                            @else
                                <p class="text-sm text-red-600 dark:text-red-400">
                                    This address is {{ number_format($this->deliveryDistanceKm, 1) }} km away — outside our delivery range.
                                </p>
                            @endif
                        @elseif (! $this->selectedAddress)
                            <p class="text-sm text-espresso-500 dark:text-cream-300">Select an address to calculate your delivery charge.</p>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm" class="mb-4">Offer code</flux:heading>

            <flux:input wire:model.live.debounce.400ms="offerCode" placeholder="Enter a promo code" :invalid="$this->offerCodeIsInvalid" />

            @if ($this->appliedOffer)
                <p class="mt-2 text-sm text-green-600 dark:text-green-400">
                    "{{ $this->appliedOffer->name }}" applied — <x-customer.price :amount="$this->discount" /> off.
                </p>
            @elseif ($this->offerCodeIsInvalid)
                <p class="mt-2 text-sm text-red-600 dark:text-red-400">
                    This code isn't valid for your order right now.
                </p>
            @endif
        </div>

        <div class="flex flex-col gap-2 rounded-2xl border border-espresso-100 bg-cream-100 p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <div class="flex items-center justify-between text-sm text-espresso-600 dark:text-cream-300">
                <span>Subtotal</span>
                <x-customer.price :amount="$this->subtotal" />
            </div>

            @if ($this->discount > 0)
                <div class="flex items-center justify-between text-sm text-green-600 dark:text-green-400">
                    <span>Discount</span>
                    <span>&minus;<x-customer.price :amount="$this->discount" class="inline" /></span>
                </div>
            @endif

            <div class="flex items-center justify-between text-sm text-espresso-600 dark:text-cream-300">
                <span>Delivery charge</span>
                <x-customer.price :amount="$this->deliveryCharge" />
            </div>

            <flux:separator class="my-1" />

            <div class="flex items-center justify-between">
                <flux:heading size="sm">Total</flux:heading>
                <x-customer.price :amount="$this->total" class="text-xl text-accent-content" />
            </div>
        </div>

        <div class="flex flex-col items-center gap-2">
            <flux:button wire:click="placeOrder" variant="primary" :disabled="! $this->canCheckout || $placingOrder" class="w-full">
                Place order
            </flux:button>

            <p class="text-center text-xs text-espresso-400 dark:text-cream-400">
                You'll pay in person — online payment isn't available yet.
            </p>
        </div>
    </div>
</div>
