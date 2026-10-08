<?php

use App\Models\Order;
use function Livewire\Volt\{layout, mount, state, title};

layout('components.layouts.admin');

state(['order' => null]);

mount(function (int $orderId) {
    // Admin function: intentionally not scoped to any particular customer —
    // any admin may look up any order by id.
    $order = Order::query()
        ->with(['items', 'delivery', 'user'])
        ->whereKey($orderId)
        ->first();

    if (! $order) {
        abort(404);
    }

    $this->order = $order;

    title("Order {$order->order_number} — Admin — OpenCafe");
});

?>

<div>
    <x-admin.page-header heading="Order {{ $order->order_number }}" />

    <div class="flex flex-col gap-6">
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <flux:subheading>Status</flux:subheading>
                    <flux:badge color="orange" size="sm" class="mt-1">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</flux:badge>
                </div>

                <div>
                    <flux:subheading>Type</flux:subheading>
                    <p class="mt-1 font-medium">{{ ucfirst(str_replace('_', ' ', $order->type->value)) }}</p>
                </div>

                <div>
                    <flux:subheading>Placed</flux:subheading>
                    <p class="mt-1 font-medium">{{ $order->placed_at?->format('d M Y, h:i A') ?? '—' }}</p>
                </div>

                <div>
                    <flux:subheading>Currency</flux:subheading>
                    <p class="mt-1 font-medium">{{ $order->currency }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:subheading class="mb-3">Customer</flux:subheading>

            <p class="font-medium">{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</p>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $order->user?->email ?? $order->guest_email ?? 'No email on file' }}</p>
        </div>

        <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
            <flux:subheading class="mb-4">Items</flux:subheading>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="py-2 font-medium">Item</th>
                            <th class="py-2 font-medium text-right">Qty</th>
                            <th class="py-2 font-medium text-right">Unit price</th>
                            <th class="py-2 font-medium text-right">Line total</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($order->items as $item)
                            <tr wire:key="admin-order-item-{{ $item->id }}">
                                <td class="py-2">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $item->item_name }}</span>

                                        @if ($item->variant_name)
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->variant_name }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-2 text-right">{{ $item->quantity }}</td>
                                <td class="py-2 text-right"><x-customer.price :amount="$item->unit_price" /></td>
                                <td class="py-2 text-right"><x-customer.price :amount="$item->line_total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($order->delivery)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
                <flux:subheading class="mb-3">Delivery snapshot</flux:subheading>

                <p class="text-sm">
                    {{ $order->delivery->recipient_name }} &middot; {{ $order->delivery->phone }}<br>
                    {{ $order->delivery->address_line_1 }}@if ($order->delivery->address_line_2), {{ $order->delivery->address_line_2 }}@endif,
                    {{ $order->delivery->city }}@if ($order->delivery->state), {{ $order->delivery->state }}@endif@if ($order->delivery->postal_code) {{ $order->delivery->postal_code }}@endif
                    {{ $order->delivery->country }}
                </p>

                <div class="mt-3 grid grid-cols-1 gap-3 text-sm text-zinc-500 sm:grid-cols-3 dark:text-zinc-400">
                    @if ($order->delivery->latitude !== null && $order->delivery->longitude !== null)
                        <span>Coordinates: {{ $order->delivery->latitude }}, {{ $order->delivery->longitude }}</span>
                    @endif
                    <span>Distance: {{ number_format((float) $order->delivery->distance_km, 1) }} km</span>
                    <span>Delivery charge: <x-customer.price :amount="$order->delivery->delivery_charge" /></span>
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-2 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900/40">
            <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
                <span>Subtotal</span>
                <x-customer.price :amount="$order->subtotal" />
            </div>

            @if ((float) $order->discount_total > 0)
                <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
                    <span>Discount</span>
                    <x-customer.price :amount="$order->discount_total" />
                </div>
            @endif

            @if ((float) $order->tax_total > 0)
                <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
                    <span>Tax</span>
                    <x-customer.price :amount="$order->tax_total" />
                </div>
            @endif

            <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
                <span>Delivery charge</span>
                <x-customer.price :amount="$order->delivery_charge" />
            </div>

            <flux:separator class="my-1" />

            <div class="flex items-center justify-between">
                <flux:heading size="sm">Total</flux:heading>
                <x-customer.price :amount="$order->total" class="text-xl" />
            </div>
        </div>

        <flux:button as="a" href="{{ route('admin.orders') }}" wire:navigate variant="ghost" class="w-fit">
            Back to orders
        </flux:button>
    </div>
</div>
