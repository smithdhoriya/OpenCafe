<?php

use App\Enums\UserRole;
use App\Models\User;
use function Livewire\Volt\{layout, mount, state, title};

layout('components.layouts.admin');

state(['customer' => null]);

mount(function (int $userId) {
    $customer = User::query()
        ->where('role', UserRole::Customer)
        ->with(['customerAddresses', 'orders' => fn ($query) => $query->orderByDesc('placed_at')->orderByDesc('id')])
        ->whereKey($userId)
        ->first();

    if (! $customer) {
        abort(404);
    }

    $this->customer = $customer;

    title("{$customer->name} — Admin — OpenCafe");
});

?>

<div>
    <x-admin.page-header heading="{{ $customer->name }}" subheading="Customer since {{ $customer->created_at?->format('d M Y') }}" />

    <div class="flex flex-col gap-6">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <flux:subheading>Email</flux:subheading>
                    <p class="mt-1 font-medium">{{ $customer->email }}</p>
                </div>

                <div>
                    <flux:subheading>Phone</flux:subheading>
                    <p class="mt-1 font-medium">{{ $customer->phone ?? '—' }}</p>
                </div>

                <div>
                    <flux:subheading>Status</flux:subheading>
                    <flux:badge :color="$customer->is_active ? 'lime' : 'zinc'" size="sm" class="mt-1">
                        {{ $customer->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:subheading class="mb-3">Saved addresses ({{ $customer->customerAddresses->count() }})</flux:subheading>

            @if ($customer->customerAddresses->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No saved addresses.</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach ($customer->customerAddresses as $address)
                        <div wire:key="admin-customer-address-{{ $address->id }}" class="rounded-xl border border-zinc-100 p-3 text-sm dark:border-zinc-700">
                            <div class="flex items-center gap-2 font-medium">
                                {{ $address->label ?: 'Address' }}
                                @if ($address->is_default)
                                    <flux:badge color="orange" size="sm">Default</flux:badge>
                                @endif
                            </div>
                            <p class="text-zinc-500 dark:text-zinc-400">
                                {{ $address->address_line_1 }}, {{ $address->city }}@if ($address->state), {{ $address->state }}@endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:subheading class="mb-3">Orders ({{ $customer->orders->count() }})</flux:subheading>

            @if ($customer->orders->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">No orders placed yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="py-2 font-medium">Order</th>
                                <th class="py-2 font-medium">Status</th>
                                <th class="py-2 font-medium text-right">Total</th>
                                <th class="py-2 font-medium">Placed</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($customer->orders as $order)
                                <tr wire:key="admin-customer-order-{{ $order->id }}">
                                    <td class="py-2 font-medium">{{ $order->order_number }}</td>
                                    <td class="py-2">
                                        <flux:badge color="orange" size="sm">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</flux:badge>
                                    </td>
                                    <td class="py-2 text-right"><x-customer.price :amount="$order->total" /></td>
                                    <td class="py-2 text-zinc-500 dark:text-zinc-400">{{ $order->placed_at?->format('d M Y') }}</td>
                                    <td class="py-2 text-right">
                                        <flux:button as="a" href="{{ route('admin.orders.show', $order) }}" wire:navigate variant="ghost" size="sm">
                                            View
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <flux:button as="a" href="{{ route('admin.customers') }}" wire:navigate variant="ghost" class="w-fit">
            Back to customers
        </flux:button>
    </div>
</div>
