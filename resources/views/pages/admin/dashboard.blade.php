<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use function Livewire\Volt\{computed, layout, title};

layout('components.layouts.admin');
title('Admin Dashboard — OpenCafe');

$totalOrders = computed(fn () => Order::query()->count());

$activeOrders = computed(fn () => Order::query()->whereNotIn('status', [
    OrderStatus::Completed,
    OrderStatus::Cancelled,
    OrderStatus::Refunded,
])->count());

$totalRevenue = computed(fn () => (float) Order::query()->where('payment_status', PaymentStatus::Paid)->sum('total'));

$menuItems = computed(fn () => MenuItem::query()->count());

$customers = computed(fn () => User::query()->where('role', UserRole::Customer)->count());

$statusCounts = computed(function () {
    $counts = Order::query()
        ->selectRaw('status, count(*) as aggregate')
        ->groupBy('status')
        ->pluck('aggregate', 'status');

    return collect(OrderStatus::cases())->mapWithKeys(
        fn (OrderStatus $status) => [$status->value => $counts->get($status->value, 0)]
    );
});

$recentOrders = computed(fn () => Order::query()
    ->with('user')
    ->latest('placed_at')
    ->take(8)
    ->get());

?>

<div>
    <div class="mb-6 flex flex-col gap-1">
        <flux:heading size="xl">Welcome back, {{ auth()->user()->name }}</flux:heading>
        <flux:subheading>Here's what's happening across OpenCafe right now.</flux:subheading>
    </div>

    {{-- Key statistics --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Total Orders', 'value' => number_format($this->totalOrders), 'icon' => 'shopping-bag', 'money' => false],
            ['label' => 'Active Orders', 'value' => number_format($this->activeOrders), 'icon' => 'clock', 'money' => false],
            ['label' => 'Total Revenue', 'value' => number_format($this->totalRevenue, 2), 'icon' => 'banknotes', 'money' => true],
            ['label' => 'Customers', 'value' => number_format($this->customers), 'icon' => 'users', 'money' => false],
        ] as $card)
            <div wire:key="stat-{{ $card['label'] }}" class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <flux:icon :icon="$card['icon']" variant="outline" class="size-5" />
                </div>

                <div>
                    <flux:subheading>{{ $card['label'] }}</flux:subheading>
                    <flux:heading size="xl" class="mt-1">
                        @if ($card['money'])
                            <x-customer.currency-symbol />{{ $card['value'] }}
                        @else
                            {{ $card['value'] }}
                        @endif
                    </flux:heading>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Order status overview --}}
    <div class="mt-8">
        <flux:heading size="lg">Order Status Overview</flux:heading>

        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            @foreach (\App\Enums\OrderStatus::cases() as $status)
                <div wire:key="status-{{ $status->value }}" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <x-admin.status-badge :status="$status" />
                    <flux:heading size="lg" class="mt-2">{{ number_format($this->statusCounts->get($status->value, 0)) }}</flux:heading>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Recent orders --}}
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">Recent Orders</flux:heading>
                <flux:button as="a" href="{{ route('admin.orders') }}" wire:navigate variant="ghost" size="sm">
                    View all
                </flux:button>
            </div>

            @if ($this->recentOrders->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-16 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                    No orders have been placed yet.
                </div>
            @else
                <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full min-w-[640px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3 font-medium">Order</th>
                                <th class="px-4 py-3 font-medium">Customer</th>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Total</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900/40">
                            @foreach ($this->recentOrders as $order)
                                <tr wire:key="recent-order-{{ $order->id }}">
                                    <td class="px-4 py-3 font-medium whitespace-nowrap">
                                        <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="hover:underline">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-zinc-500 dark:text-zinc-400">{{ $order->placed_at?->format('d M Y, h:i A') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <x-admin.status-badge :status="$order->status" />
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap"><x-customer.price :amount="$order->total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Quick actions --}}
        <div>
            <flux:heading size="lg" class="mb-3">Quick Actions</flux:heading>

            <div class="grid grid-cols-1 gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                @foreach ([
                    ['label' => 'View Orders', 'route' => 'admin.orders', 'icon' => 'shopping-bag'],
                    ['label' => 'Manage Menu', 'route' => 'admin.menu', 'icon' => 'book-open'],
                    ['label' => 'Manage Categories', 'route' => 'admin.categories', 'icon' => 'squares-2x2'],
                    ['label' => 'Manage Customers', 'route' => 'admin.customers', 'icon' => 'users'],
                    ['label' => 'Offers & Combos', 'route' => 'admin.offers', 'icon' => 'tag'],
                    ['label' => 'Reviews', 'route' => 'admin.reviews', 'icon' => 'star'],
                    ['label' => 'Cafe Settings', 'route' => 'admin.settings', 'icon' => 'cog'],
                ] as $action)
                    <flux:button
                        as="a"
                        href="{{ route($action['route']) }}"
                        wire:navigate
                        :icon="$action['icon']"
                        variant="ghost"
                        class="justify-start"
                        wire:key="quick-action-{{ $action['route'] }}"
                    >
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach
            </div>

            <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
                <flux:subheading>Menu Items</flux:subheading>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->menuItems) }}</flux:heading>
            </div>
        </div>
    </div>
</div>
