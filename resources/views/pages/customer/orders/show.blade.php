<?php

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use function Livewire\Volt\{layout, mount, state, title};

layout('components.layouts.customer');

state(['order' => null]);

mount(function (int $orderId) {
    // Scoped to the authenticated user: a tampered order id in the URL never
    // resolves to another customer's order.
    $order = auth()->user()->orders()
        ->with(['items', 'delivery'])
        ->whereKey($orderId)
        ->first();

    if (! $order) {
        abort(404);
    }

    $this->order = $order;

    title("Order {$order->order_number} — OpenCafe");
});

$cancel = function (CancelOrder $cancelOrder) {
    try {
        $cancelOrder->handle(auth()->user(), $this->order->id);
    } catch (ValidationException $e) {
        Flux::toast(
            text: $e->validator->errors()->first(),
            heading: 'Unable to cancel order',
            variant: 'danger',
        );

        return;
    }

    $this->order->status = OrderStatus::Cancelled;

    Flux::toast(text: 'Your order has been cancelled.', heading: 'Order cancelled', variant: 'success');
};

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
    <div class="mb-8 flex flex-col gap-1">
        <flux:badge color="orange" size="sm" class="w-fit">{{ ucfirst(str_replace('_', ' ', $order->status->value)) }}</flux:badge>
        <flux:heading size="xl">Order {{ $order->order_number }}</flux:heading>
        <p class="text-espresso-600 dark:text-cream-300">
            {{ ucfirst($order->type->value) }} order placed {{ $order->placed_at?->diffForHumans() }}.
        </p>
    </div>

    <div class="flex flex-col gap-6">
        <div class="rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm" class="mb-4">Items</flux:heading>

            <div class="flex flex-col gap-3">
                @foreach ($order->items as $item)
                    <div wire:key="order-item-{{ $item->id }}" class="flex items-center justify-between gap-4 text-sm">
                        <div class="flex flex-col">
                            <span class="font-medium text-espresso-800 dark:text-cream-100">{{ $item->quantity }} &times; {{ $item->item_name }}</span>

                            @if ($item->variant_name)
                                <span class="text-espresso-500 dark:text-cream-300">{{ $item->variant_name }}</span>
                            @endif
                        </div>

                        <x-customer.price :amount="$item->line_total" />
                    </div>
                @endforeach
            </div>
        </div>

        @if ($order->delivery)
            <div class="rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
                <flux:heading size="sm" class="mb-4">Delivering to</flux:heading>

                <p class="text-sm text-espresso-600 dark:text-cream-300">
                    {{ $order->delivery->recipient_name }} &middot; {{ $order->delivery->phone }}<br>
                    {{ $order->delivery->address_line_1 }}@if ($order->delivery->address_line_2), {{ $order->delivery->address_line_2 }}@endif,
                    {{ $order->delivery->city }}@if ($order->delivery->state), {{ $order->delivery->state }}@endif@if ($order->delivery->postal_code) {{ $order->delivery->postal_code }}@endif
                </p>

                <p class="mt-2 text-sm text-espresso-500 dark:text-cream-300">
                    {{ number_format((float) $order->delivery->distance_km, 1) }} km away
                </p>
            </div>
        @endif

        <div class="flex flex-col gap-2 rounded-2xl border border-espresso-100 bg-cream-100 p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <div class="flex items-center justify-between text-sm text-espresso-600 dark:text-cream-300">
                <span>Subtotal</span>
                <x-customer.price :amount="$order->subtotal" />
            </div>

            <div class="flex items-center justify-between text-sm text-espresso-600 dark:text-cream-300">
                <span>Delivery charge</span>
                <x-customer.price :amount="$order->delivery_charge" />
            </div>

            <flux:separator class="my-1" />

            <div class="flex items-center justify-between">
                <flux:heading size="sm">Total</flux:heading>
                <x-customer.price :amount="$order->total" class="text-xl text-accent-content" />
            </div>
        </div>

        <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-300">
            Payment is not collected online yet — this order is marked <strong>unpaid</strong> and will be settled in person.
        </div>

        @if ($order->status === OrderStatus::Pending)
            <flux:button
                wire:click="cancel"
                wire:confirm="Cancel this order? This cannot be undone."
                variant="danger"
                class="w-full"
            >
                Cancel order
            </flux:button>
        @endif

        <flux:button as="a" href="{{ route('menu.index') }}" wire:navigate variant="primary" class="w-full">
            Back to menu
        </flux:button>
    </div>
</div>
