<?php

use Flux\Flux;
use function Livewire\Volt\{computed, layout, state, title};

layout('components.layouts.customer');
title('Delivery Addresses — OpenCafe');

state([
    'showForm' => false,
    'editingId' => null,
    'label' => '',
    'recipient_name' => '',
    'phone' => '',
    'address_line_1' => '',
    'address_line_2' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'country' => 'India',
    'latitude' => '',
    'longitude' => '',
    'delivery_instructions' => '',
]);

$addresses = computed(function () {
    return auth()->user()->customerAddresses()->orderByDesc('is_default')->orderByDesc('created_at')->get();
});

$resetForm = function () {
    $this->editingId = null;
    $this->label = '';
    $this->recipient_name = '';
    $this->phone = '';
    $this->address_line_1 = '';
    $this->address_line_2 = '';
    $this->city = '';
    $this->state = '';
    $this->postal_code = '';
    $this->country = 'India';
    $this->latitude = '';
    $this->longitude = '';
    $this->delivery_instructions = '';
    $this->resetErrorBag();
};

$startCreate = function () {
    $this->resetForm();
    $this->showForm = true;
};

$startEdit = function (int $addressId) {
    $address = auth()->user()->customerAddresses()->whereKey($addressId)->first();

    if (! $address) {
        return;
    }

    $this->editingId = $address->id;
    $this->label = $address->label ?? '';
    $this->recipient_name = $address->recipient_name;
    $this->phone = $address->phone;
    $this->address_line_1 = $address->address_line_1;
    $this->address_line_2 = $address->address_line_2 ?? '';
    $this->city = $address->city;
    $this->state = $address->state ?? '';
    $this->postal_code = $address->postal_code ?? '';
    $this->country = $address->country;
    $this->latitude = (string) $address->latitude;
    $this->longitude = (string) $address->longitude;
    $this->delivery_instructions = $address->delivery_instructions ?? '';

    $this->showForm = true;
};

$save = function () {
    $validated = $this->validate([
        'label' => ['nullable', 'string', 'max:50'],
        'recipient_name' => ['required', 'string', 'max:150'],
        'phone' => ['required', 'string', 'max:30'],
        'address_line_1' => ['required', 'string', 'max:255'],
        'address_line_2' => ['nullable', 'string', 'max:255'],
        'city' => ['required', 'string', 'max:100'],
        'state' => ['nullable', 'string', 'max:100'],
        'postal_code' => ['nullable', 'string', 'max:20'],
        'country' => ['required', 'string', 'max:100'],
        'latitude' => ['required', 'numeric', 'between:-90,90'],
        'longitude' => ['required', 'numeric', 'between:-180,180'],
        'delivery_instructions' => ['nullable', 'string', 'max:1000'],
    ]);

    foreach (['label', 'address_line_2', 'state', 'postal_code', 'delivery_instructions'] as $optional) {
        $validated[$optional] = filled($validated[$optional]) ? $validated[$optional] : null;
    }

    $user = auth()->user();

    if ($this->editingId) {
        $address = $user->customerAddresses()->whereKey($this->editingId)->first();

        if (! $address) {
            $this->showForm = false;
            $this->resetForm();

            return;
        }

        $address->update($validated);

        Flux::toast(text: 'Your changes have been saved.', heading: 'Address updated', variant: 'success');
    } else {
        $validated['is_default'] = ! $user->customerAddresses()->exists();

        $user->customerAddresses()->create($validated);

        Flux::toast(text: 'Your new address has been saved.', heading: 'Address added', variant: 'success');
    }

    $this->showForm = false;
    $this->resetForm();
};

$setDefault = function (int $addressId) {
    $user = auth()->user();
    $address = $user->customerAddresses()->whereKey($addressId)->first();

    if (! $address || $address->is_default) {
        return;
    }

    $user->customerAddresses()->where('is_default', true)->update(['is_default' => false]);
    $address->update(['is_default' => true]);
};

$delete = function (int $addressId) {
    $user = auth()->user();
    $address = $user->customerAddresses()->whereKey($addressId)->first();

    if (! $address) {
        return;
    }

    $wasDefault = $address->is_default;

    $address->delete();

    if ($wasDefault) {
        $user->customerAddresses()->orderBy('id')->first()?->update(['is_default' => true]);
    }

    Flux::toast(text: 'The address has been removed.', heading: 'Address removed', variant: 'success');
};

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Delivery addresses</flux:heading>
            <p class="mt-1 text-espresso-600 dark:text-cream-300">Manage the addresses we deliver your order to.</p>
        </div>

        <flux:button wire:click="startCreate" variant="primary" icon="plus">Add address</flux:button>
    </div>

    @if ($this->addresses->isEmpty())
        <div class="relative flex flex-col items-center gap-5 overflow-hidden rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-16 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <div class="relative flex size-20 items-center justify-center rounded-full bg-orange-100 text-orange-700 shadow-sm dark:bg-espresso-700 dark:text-orange-400">
                <flux:icon.map-pin variant="outline" class="size-9" />
            </div>

            <div class="relative flex flex-col gap-2">
                <flux:heading size="xl">No saved addresses yet</flux:heading>
                <p class="max-w-md text-espresso-600 dark:text-cream-300">
                    Add a delivery address so we know exactly where to bring your order.
                </p>
            </div>

            <flux:button wire:click="startCreate" variant="primary" class="relative">Add your first address</flux:button>
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->addresses as $address)
                <div wire:key="address-{{ $address->id }}" class="flex flex-col gap-3 rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <flux:heading size="sm">{{ $address->label ?: 'Address' }}</flux:heading>

                                @if ($address->is_default)
                                    <flux:badge color="orange" size="sm">Default</flux:badge>
                                @endif
                            </div>

                            <span class="text-sm text-espresso-600 dark:text-cream-300">{{ $address->recipient_name }} &middot; {{ $address->phone }}</span>

                            <span class="text-sm text-espresso-600 dark:text-cream-300">
                                {{ $address->address_line_1 }}@if ($address->address_line_2), {{ $address->address_line_2 }}@endif,
                                {{ $address->city }}@if ($address->state), {{ $address->state }}@endif@if ($address->postal_code) {{ $address->postal_code }}@endif
                            </span>

                            <span class="text-xs text-espresso-400 dark:text-cream-400">{{ $address->latitude }}, {{ $address->longitude }}</span>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @unless ($address->is_default)
                                <flux:button wire:click="setDefault({{ $address->id }})" variant="ghost" size="sm">Set as default</flux:button>
                            @endunless

                            <flux:button wire:click="startEdit({{ $address->id }})" variant="ghost" size="sm" icon="pencil" square aria-label="Edit address" />

                            <flux:button
                                wire:click="delete({{ $address->id }})"
                                wire:confirm="Remove this address?"
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                square
                                aria-label="Delete address"
                            />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg" @close="resetForm">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Edit address' : 'Add address' }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="label" label="Label (optional)" placeholder="Home, Work..." />
                <flux:input wire:model="recipient_name" label="Recipient name" required />
                <flux:input wire:model="phone" label="Phone" required class="sm:col-span-2" />
                <flux:input wire:model="address_line_1" label="Full address" required class="sm:col-span-2" />
                <flux:input wire:model="address_line_2" label="Apartment, suite, etc. (optional)" class="sm:col-span-2" />
                <flux:input wire:model="city" label="City" required />
                <flux:input wire:model="state" label="State" />
                <flux:input wire:model="postal_code" label="Postal code" />
                <flux:input wire:model="country" label="Country" required />
                <flux:input wire:model="latitude" label="Latitude" placeholder="e.g. 12.9716" required />
                <flux:input wire:model="longitude" label="Longitude" placeholder="e.g. 77.5946" required />
                <flux:textarea wire:model="delivery_instructions" label="Delivery instructions (optional)" class="sm:col-span-2" rows="2" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save address</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
