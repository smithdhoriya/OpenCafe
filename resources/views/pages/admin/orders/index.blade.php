<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use function Livewire\Volt\{computed, layout, state, title, updated, usesPagination};

usesPagination();

layout('components.layouts.admin');
title('Orders — Admin — OpenCafe');

state([
    'search' => '',
    'status' => '',
    'type' => '',
]);

updated([
    'search' => fn () => $this->resetPage(),
    'status' => fn () => $this->resetPage(),
    'type' => fn () => $this->resetPage(),
]);

$orders = computed(function () {
    $term = '%'.trim($this->search).'%';

    return Order::query()
        ->with('user')
        ->when(trim($this->search) !== '', function ($query) use ($term) {
            $query->where(function ($inner) use ($term) {
                $inner->where('order_number', 'like', $term)
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        })
        ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
        ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
        ->orderByDesc('placed_at')
        ->orderByDesc('id')
        ->paginate(15);
});

?>

<div>
    <x-admin.page-header heading="Orders" subheading="Browse every order placed through OpenCafe." />

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <flux:input
            wire:model.live.debounce.400ms="search"
            placeholder="Search order #, customer name or email..."
            icon="magnifying-glass"
            class="sm:col-span-1"
        />

        <flux:select wire:model.live="status">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (OrderStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ ucfirst(str_replace('_', ' ', $case->value)) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="type">
            <flux:select.option value="">All types</flux:select.option>
            @foreach (OrderType::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ ucfirst(str_replace('_', ' ', $case->value)) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->orders->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-16 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
            No orders match your filters.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-[720px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right">Subtotal</th>
                        <th class="px-4 py-3 font-medium text-right">Delivery</th>
                        <th class="px-4 py-3 font-medium text-right">Total</th>
                        <th class="px-4 py-3 font-medium">Placed</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900/40">
                    @foreach ($this->orders as $order)
                        <tr wire:key="admin-order-{{ $order->id }}">
                            <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $order->order_number }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span>{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $order->user?->email ?? $order->guest_email ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $order->type->value)) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <flux:badge color="orange" size="sm">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-customer.price :amount="$order->subtotal" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-customer.price :amount="$order->delivery_charge" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-customer.price :amount="$order->total" /></td>
                            <td class="px-4 py-3 whitespace-nowrap text-zinc-500 dark:text-zinc-400">{{ $order->placed_at?->format('d M Y, h:i A') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <flux:button as="a" href="{{ route('admin.orders.show', $order) }}" wire:navigate variant="ghost" size="sm">
                                    View
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->orders->links() }}
        </div>
    @endif
</div>
