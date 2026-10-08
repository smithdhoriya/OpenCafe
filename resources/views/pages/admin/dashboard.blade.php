<?php

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.admin', ['heading' => 'Dashboard', 'subheading' => 'An overview of OpenCafe — more detail lands in later steps.']);
title('Admin Dashboard — OpenCafe');

$totalOrders = computed(fn () => Order::query()->count());

$pendingOrders = computed(fn () => Order::query()->where('status', OrderStatus::Pending)->count());

$menuItems = computed(fn () => MenuItem::query()->count());

$customers = computed(fn () => User::query()->where('role', UserRole::Customer)->count());

?>

<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total Orders', 'value' => $this->totalOrders],
            ['label' => 'Pending Orders', 'value' => $this->pendingOrders],
            ['label' => 'Menu Items', 'value' => $this->menuItems],
            ['label' => 'Customers', 'value' => $this->customers],
        ] as $card)
            <div wire:key="stat-{{ $card['label'] }}" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
                <flux:subheading>{{ $card['label'] }}</flux:subheading>
                <flux:heading size="xl" class="mt-1">{{ number_format($card['value']) }}</flux:heading>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
        More dashboard detail — trends, recent activity, and alerts — will be added in a later step.
    </div>
</div>
