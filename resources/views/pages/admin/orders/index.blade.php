<?php

use App\Actions\Orders\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
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

$advanceStatus = function (int $orderId, UpdateOrderStatus $action) {
    $order = Order::query()->whereKey($orderId)->first();

    $next = $order?->status->nextForwardStatus($order->type);

    if (! $order || ! $next) {
        Flux::toast(text: 'This order has no further status to advance to.', heading: 'Status update failed', variant: 'danger');

        return;
    }

    try {
        $action->handle(auth()->user(), $orderId, $next);
    } catch (ValidationException $e) {
        Flux::toast(text: $e->validator->errors()->first(), heading: 'Status update failed', variant: 'danger');

        return;
    }

    Flux::toast(text: "Order {$order->order_number} is now ".ucfirst(str_replace('_', ' ', $next->value)).'.', heading: 'Status updated', variant: 'success');
};

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
        {{-- Tablet/mobile: a stacked card per order. The full table below is too wide to use
             comfortably once it has to compete with the sidebar for space, so below `lg` we
             swap to cards instead of forcing a horizontal scroll on a dense grid. --}}
        <div class="flex flex-col gap-3 lg:hidden">
            @foreach ($this->orders as $order)
                @php $next = $order->status->nextForwardStatus($order->type); @endphp

                <div wire:key="admin-order-card-{{ $order->id }}" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $order->order_number }}</p>
                            <p class="text-sm">{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $order->user?->email ?? $order->guest_email ?? '—' }}</p>
                        </div>

                        <x-admin.status-badge :status="$order->status" />
                    </div>

                    <div class="mt-3 flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ ucfirst(str_replace('_', ' ', $order->type->value)) }} &middot; {{ $order->placed_at?->format('d M Y, h:i A') }}</span>
                        <x-customer.price :amount="$order->total" class="font-semibold text-zinc-900 dark:text-zinc-100" />
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        @if ($next)
                            <flux:button
                                wire:click="advanceStatus({{ $order->id }})"
                                wire:loading.attr="disabled"
                                wire:target="advanceStatus({{ $order->id }})"
                                wire:confirm="Mark order {{ $order->order_number }} as {{ ucfirst(str_replace('_', ' ', $next->value)) }}?"
                                variant="primary"
                                size="sm"
                            >
                                Mark {{ ucfirst(str_replace('_', ' ', $next->value)) }}
                            </flux:button>
                        @endif

                        <flux:button as="a" href="{{ route('admin.orders.show', $order) }}" wire:navigate variant="ghost" size="sm">
                            View
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Desktop: the full table. Subtotal/delivery breakdown stays on the order detail
             page — trimming them here keeps the row narrow enough that it fits most desktop
             viewports without scrolling. The Actions column stays pinned to the right edge of
             the scroll container (rather than the right edge of the page) so status actions
             are never clipped even if the table does need to scroll on a narrower screen. --}}
        <div class="hidden overflow-x-auto rounded-2xl border border-zinc-200 lg:block dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2.5 font-medium">Order</th>
                        <th class="px-3 py-2.5 font-medium">Customer</th>
                        <th class="px-3 py-2.5 font-medium">Type</th>
                        <th class="px-3 py-2.5 font-medium">Status</th>
                        <th class="px-3 py-2.5 font-medium text-right">Total</th>
                        <th class="px-3 py-2.5 font-medium">Placed</th>
                        <th class="sticky right-0 bg-zinc-50 px-3 py-2.5 text-right font-medium shadow-[-8px_0_8px_-6px_rgba(0,0,0,0.08)] dark:bg-zinc-900/60 dark:shadow-[-8px_0_8px_-6px_rgba(0,0,0,0.3)]">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900/40">
                    @foreach ($this->orders as $order)
                        <tr wire:key="admin-order-{{ $order->id }}">
                            <td class="px-3 py-2.5 font-medium whitespace-nowrap">{{ $order->order_number }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-col">
                                    <span>{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $order->user?->email ?? $order->guest_email ?? '—' }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $order->type->value)) }}</td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <x-admin.status-badge :status="$order->status" />
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap"><x-customer.price :amount="$order->total" /></td>
                            <td class="px-3 py-2.5 whitespace-nowrap text-zinc-500 dark:text-zinc-400">{{ $order->placed_at?->format('d M Y, h:i A') }}</td>
                            <td class="sticky right-0 bg-white px-3 py-2.5 text-right whitespace-nowrap shadow-[-8px_0_8px_-6px_rgba(0,0,0,0.08)] dark:bg-zinc-900 dark:shadow-[-8px_0_8px_-6px_rgba(0,0,0,0.3)]">
                                <div class="flex items-center justify-end gap-1.5">
                                    @php $next = $order->status->nextForwardStatus($order->type); @endphp

                                    @if ($next)
                                        <flux:button
                                            wire:click="advanceStatus({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="advanceStatus({{ $order->id }})"
                                            wire:confirm="Mark order {{ $order->order_number }} as {{ ucfirst(str_replace('_', ' ', $next->value)) }}?"
                                            variant="ghost"
                                            size="sm"
                                        >
                                            Mark {{ ucfirst(str_replace('_', ' ', $next->value)) }}
                                        </flux:button>
                                    @endif

                                    <flux:button as="a" href="{{ route('admin.orders.show', $order) }}" wire:navigate variant="ghost" size="sm">
                                        View
                                    </flux:button>
                                </div>
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
