<?php

use App\Enums\SettingType;
use App\Models\CafeSetting;
use App\Models\DeliveryRate;
use Flux\Flux;
use function Livewire\Volt\{computed, layout, mount, state, title};

layout('components.layouts.admin');
title('Cafe Settings — Admin — OpenCafe');

state([
    'values' => [],
    'toggles' => [],

    'showRateForm' => false,
    'editingRateId' => null,
    'rate_min_distance_km' => '',
    'rate_max_distance_km' => '',
    'rate_charge' => '',
    'rate_is_active' => true,
    'rate_sort_order' => 0,
]);

mount(function () {
    foreach (CafeSetting::query()->get() as $setting) {
        if ($setting->type === SettingType::Boolean) {
            $this->toggles[$setting->key] = filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
        } else {
            $this->values[$setting->key] = $setting->value ?? '';
        }
    }
});

$settingsByGroup = computed(function () {
    return CafeSetting::query()
        ->orderBy('group')
        ->orderBy('label')
        ->get()
        ->groupBy('group');
});

$deliveryRates = computed(fn () => DeliveryRate::query()->orderBy('min_distance_km')->orderBy('sort_order')->get());

$saveSettings = function () {
    $settings = CafeSetting::query()->get();

    $rules = [];

    foreach ($settings as $setting) {
        $field = $setting->type === SettingType::Boolean ? "toggles.{$setting->key}" : "values.{$setting->key}";

        $rules[$field] = match ($setting->type) {
            SettingType::Boolean => ['boolean'],
            SettingType::Integer => ['required', 'integer'],
            SettingType::Decimal => ['required', 'numeric'],
            SettingType::Json => ['required', 'string'],
            SettingType::String => ['required', 'string', 'max:1000'],
        };
    }

    $this->validate($rules);

    foreach ($settings as $setting) {
        if ($setting->type === SettingType::Json) {
            json_decode($this->values[$setting->key] ?? '', true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError("values.{$setting->key}", 'This must be valid JSON.');

                return;
            }
        }

        if ($setting->type === SettingType::Boolean) {
            $setting->update(['value' => ($this->toggles[$setting->key] ?? false) ? '1' : '0']);
        } else {
            $setting->update(['value' => $this->values[$setting->key] ?? '']);
        }
    }

    Flux::toast(text: 'Cafe settings updated.', heading: 'Saved', variant: 'success');
};

$startEditRate = function (int $rateId) {
    $rate = DeliveryRate::query()->whereKey($rateId)->first();

    if (! $rate) {
        return;
    }

    $this->editingRateId = $rate->id;
    $this->rate_min_distance_km = (string) $rate->min_distance_km;
    $this->rate_max_distance_km = $rate->max_distance_km !== null ? (string) $rate->max_distance_km : '';
    $this->rate_charge = (string) $rate->charge;
    $this->rate_is_active = $rate->is_active;
    $this->rate_sort_order = $rate->sort_order;

    $this->showRateForm = true;
};

$saveRate = function () {
    $validated = $this->validate([
        'rate_min_distance_km' => ['required', 'numeric', 'min:0'],
        'rate_max_distance_km' => ['nullable', 'numeric', 'gt:rate_min_distance_km'],
        'rate_charge' => ['required', 'numeric', 'min:0'],
        'rate_is_active' => ['boolean'],
        'rate_sort_order' => ['integer', 'min:0'],
    ]);

    $rate = DeliveryRate::query()->whereKey($this->editingRateId)->first();

    if (! $rate) {
        $this->showRateForm = false;

        return;
    }

    $rate->update([
        'min_distance_km' => $validated['rate_min_distance_km'],
        'max_distance_km' => filled($validated['rate_max_distance_km']) ? $validated['rate_max_distance_km'] : null,
        'charge' => $validated['rate_charge'],
        'is_active' => $validated['rate_is_active'],
        'sort_order' => $validated['rate_sort_order'],
    ]);

    $this->showRateForm = false;
    $this->editingRateId = null;

    Flux::toast(text: 'Delivery rate updated.', heading: 'Saved', variant: 'success');
};

?>

<div>
    <x-admin.page-header heading="Cafe Settings" subheading="General information and delivery configuration." />

    <form wire:submit="saveSettings" class="flex flex-col gap-6">
        @foreach ($this->settingsByGroup as $group => $settings)
            <div class="rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/40 dark:bg-zinc-900">
                <flux:subheading class="mb-4">{{ ucfirst($group) }}</flux:subheading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($settings as $setting)
                        <div class="{{ $setting->type === App\Enums\SettingType::Json ? 'sm:col-span-2' : '' }}">
                            @if ($setting->type === App\Enums\SettingType::Boolean)
                                <flux:checkbox wire:model="toggles.{{ $setting->key }}" label="{{ $setting->label }}" />
                            @elseif ($setting->type === App\Enums\SettingType::Json)
                                <flux:textarea wire:model="values.{{ $setting->key }}" label="{{ $setting->label }}" rows="3" />
                            @elseif ($setting->type === App\Enums\SettingType::Decimal || $setting->type === App\Enums\SettingType::Integer)
                                <flux:input wire:model="values.{{ $setting->key }}" label="{{ $setting->label }}" type="number" step="any" />
                            @else
                                <flux:input wire:model="values.{{ $setting->key }}" label="{{ $setting->label }}" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div>
            <flux:button type="submit" variant="primary">Save settings</flux:button>
        </div>
    </form>

    <div class="mt-8 rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/40 dark:bg-zinc-900">
        <flux:subheading class="mb-4">Delivery rate tiers</flux:subheading>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[520px] divide-y divide-emerald-100 text-left text-sm dark:divide-emerald-900/40">
                <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="py-2 font-medium">Distance</th>
                        <th class="py-2 font-medium text-right">Charge</th>
                        <th class="py-2 font-medium">Active</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-emerald-100 dark:divide-emerald-900/40">
                    @foreach ($this->deliveryRates as $rate)
                        <tr wire:key="admin-delivery-rate-{{ $rate->id }}">
                            <td class="py-2">
                                {{ number_format((float) $rate->min_distance_km, 1) }}&ndash;{{ $rate->max_distance_km !== null ? number_format((float) $rate->max_distance_km, 1).' km' : 'and beyond' }}
                            </td>
                            <td class="py-2 text-right"><x-customer.price :amount="$rate->charge" /></td>
                            <td class="py-2">
                                <flux:badge :color="$rate->is_active ? 'lime' : 'zinc'" size="sm">
                                    {{ $rate->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="py-2 text-right">
                                <flux:button wire:click="startEditRate({{ $rate->id }})" variant="ghost" size="sm" icon="pencil" square aria-label="Edit rate" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <flux:modal wire:model.self="showRateForm" class="w-full max-w-md">
        <form wire:submit="saveRate" class="space-y-6">
            <flux:heading size="lg">Edit delivery rate</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="rate_min_distance_km" label="Min distance (km)" type="number" step="0.01" min="0" required />
                <flux:input wire:model="rate_max_distance_km" label="Max distance (km, optional)" type="number" step="0.01" min="0" />
                <flux:input wire:model="rate_charge" label="Charge" type="number" step="0.01" min="0" required class="col-span-2" />
                <flux:input wire:model="rate_sort_order" label="Sort order" type="number" min="0" />
                <flux:checkbox wire:model="rate_is_active" label="Active" class="self-center" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showRateForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save rate</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
