<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CafeSetting;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\Ai\AdminAnalyticsService;
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

// Chart data for the analytics panel below — reuses the same read-only,
// pre-aggregated AdminAnalyticsService already built for the Admin AI
// assistant, so the revenue/ranking rules stay identical everywhere.
$salesTrend = computed(function () {
    $analytics = app(AdminAnalyticsService::class);
    $window = $analytics->standardWindows()['last_30_days'];

    return $analytics->dailySalesTrend($window['from'], $window['to']);
});

$topSellingItems = computed(function () {
    $analytics = app(AdminAnalyticsService::class);
    $window = $analytics->standardWindows()['last_30_days'];

    return $analytics->topSellingItems($window['from'], $window['to'], limit: 5);
});

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
            <div wire:key="stat-{{ $card['label'] }}" class="flex items-start gap-4 rounded-2xl border border-emerald-100 bg-white p-5 dark:border-emerald-900/40 dark:bg-zinc-900">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
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

    {{-- Business analytics --}}
    <div class="mt-8 rounded-2xl border border-emerald-100 bg-white p-4 dark:border-emerald-900/40 dark:bg-zinc-900 sm:p-6">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">Business Analytics</flux:heading>
            <flux:subheading>Last 30 days</flux:subheading>
        </div>

        <div class="grid grid-cols-1 gap-6">
            {{-- Sales trend --}}
            @php
                $trend = $this->salesTrend;
                $totalTrendRevenue = collect($trend)->sum('revenue');
                $symbol = CafeSetting::currencySymbol();
            @endphp

            <div>
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-1">
                    <flux:subheading>Daily revenue (paid orders only)</flux:subheading>
                    <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                        {{ $symbol }}{{ number_format($totalTrendRevenue, 2) }} total
                    </span>
                </div>

                @if ($totalTrendRevenue <= 0)
                    <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-8 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                        No paid sales recorded in the last 30 days.
                    </div>
                @else
                    @php
                        $chartWidth = 600;
                        $chartHeight = 140;
                        $maxTrendRevenue = max(1.0, collect($trend)->max('revenue'));
                        $trendCount = count($trend);
                        $stepX = $trendCount > 1 ? $chartWidth / ($trendCount - 1) : $chartWidth;

                        $trendPoints = collect($trend)->values()->map(function (array $day, int $i) use ($stepX, $chartHeight, $maxTrendRevenue) {
                            return [
                                'x' => round($i * $stepX, 2),
                                'y' => round($chartHeight - 6 - (($day['revenue'] / $maxTrendRevenue) * ($chartHeight - 16)), 2),
                            ];
                        });

                        $trendPolyline = $trendPoints->map(fn (array $p) => "{$p['x']},{$p['y']}")->implode(' ');
                        $trendArea = "0,{$chartHeight} {$trendPolyline} {$chartWidth},{$chartHeight}";
                        $trendFirstDay = \Illuminate\Support\Carbon::parse($trend[0]['date'])->format('j M');
                        $trendMidDay = \Illuminate\Support\Carbon::parse($trend[intdiv($trendCount, 2)]['date'])->format('j M');
                        $trendLastDay = \Illuminate\Support\Carbon::parse($trend[$trendCount - 1]['date'])->format('j M');
                    @endphp

                    <svg
                        viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
                        preserveAspectRatio="none"
                        class="h-36 w-full"
                        role="img"
                        aria-label="Daily paid revenue from {{ $trendFirstDay }} to {{ $trendLastDay }}, totaling {{ $symbol }}{{ number_format($totalTrendRevenue, 2) }}"
                    >
                        <polygon points="{{ $trendArea }}" fill="#10b981" fill-opacity="0.16" />
                        <polyline
                            points="{{ $trendPolyline }}"
                            fill="none"
                            stroke="#10b981"
                            stroke-width="2.5"
                            stroke-linejoin="round"
                            stroke-linecap="round"
                        />
                    </svg>

                    <div class="mt-1 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ $trendFirstDay }}</span>
                        <span>{{ $trendMidDay }}</span>
                        <span>{{ $trendLastDay }}</span>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Order status chart --}}
                <div>
                    <flux:subheading class="mb-3">Order status (all-time)</flux:subheading>

                    @php $maxStatusCount = max(1, $this->statusCounts->max()); @endphp

                    @if ($this->statusCounts->sum() <= 0)
                        <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-8 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                            No orders recorded yet.
                        </div>
                    @else
                        <div class="space-y-2.5">
                            @foreach (OrderStatus::cases() as $status)
                                @php $count = $this->statusCounts->get($status->value, 0); @endphp
                                <div wire:key="status-chart-{{ $status->value }}">
                                    <div class="mb-1 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-300">
                                        <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $status->value)) }}</span>
                                        <span>{{ number_format($count) }}</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div
                                            class="h-full rounded-full bg-emerald-500"
                                            style="width: {{ $count > 0 ? max(4, round(($count / $maxStatusCount) * 100)) : 0 }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Best-selling items --}}
                <div>
                    <flux:subheading class="mb-3">Best-selling items (last 30 days)</flux:subheading>

                    @php $topItems = $this->topSellingItems; @endphp

                    @if (empty($topItems))
                        <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-8 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                            No sales recorded in the last 30 days to rank menu items.
                        </div>
                    @else
                        @php $maxTopQuantity = max(1, collect($topItems)->max('quantity_sold')); @endphp

                        <div class="space-y-2.5">
                            @foreach ($topItems as $item)
                                <div wire:key="top-item-chart-{{ $item['menu_item_id'] }}">
                                    <div class="mb-1 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-300">
                                        <span class="truncate pr-2 font-medium">{{ $item['name'] }}</span>
                                        <span class="shrink-0">{{ number_format($item['quantity_sold']) }} sold</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div
                                            class="h-full rounded-full bg-emerald-500"
                                            style="width: {{ $item['quantity_sold'] > 0 ? max(4, round(($item['quantity_sold'] / $maxTopQuantity) * 100)) : 0 }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Order status overview --}}
    <div class="mt-8">
        <flux:heading size="lg">Order Status Overview</flux:heading>

        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
            @foreach (\App\Enums\OrderStatus::cases() as $status)
                <div wire:key="status-{{ $status->value }}" class="rounded-2xl border border-emerald-100 bg-white p-4 dark:border-emerald-900/40 dark:bg-zinc-900">
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
                <flux:button
                    as="a"
                    href="{{ route('admin.orders') }}"
                    wire:navigate
                    variant="ghost"
                    size="sm"
                    class="text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                >
                    View all
                </flux:button>
            </div>

            @if ($this->recentOrders->isEmpty())
                <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-16 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                    No orders have been placed yet.
                </div>
            @else
                {{-- Desktop/tablet: fixed-width columns so the table always fits its
                     container without a horizontal scrollbar; long order numbers and
                     customer names truncate with a native tooltip for the full value. --}}
                <div class="hidden rounded-2xl border border-emerald-100 dark:border-emerald-900/40 sm:block">
                    <table class="w-full table-fixed divide-y divide-emerald-100 text-left text-sm dark:divide-emerald-900/40">
                        <thead class="bg-emerald-50/60 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                            <tr>
                                <th class="w-[22%] px-3 py-3 font-medium">Order</th>
                                <th class="w-[24%] px-3 py-3 font-medium">Customer</th>
                                <th class="w-[20%] px-3 py-3 font-medium">Date</th>
                                <th class="w-[16%] px-3 py-3 font-medium">Status</th>
                                <th class="w-[18%] px-3 py-3 font-medium text-right">Total</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-emerald-100 bg-white dark:divide-emerald-900/40 dark:bg-zinc-900">
                            @foreach ($this->recentOrders as $order)
                                <tr wire:key="recent-order-{{ $order->id }}" class="transition hover:bg-emerald-50/40 dark:hover:bg-emerald-900/10">
                                    <td class="px-3 py-3 font-medium">
                                        <a
                                            href="{{ route('admin.orders.show', $order) }}"
                                            wire:navigate
                                            class="block truncate hover:underline"
                                            title="{{ $order->order_number }}"
                                        >
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="block truncate" title="{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}">
                                            {{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-zinc-500 dark:text-zinc-400">
                                        <span class="block truncate">{{ $order->placed_at?->format('d M Y') }}</span>
                                        <span class="block truncate text-xs">{{ $order->placed_at?->format('h:i A') }}</span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <x-admin.status-badge :status="$order->status" />
                                    </td>
                                    <td class="px-3 py-3 text-right whitespace-nowrap"><x-customer.price :amount="$order->total" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile: compact stacked rows instead of a cramped/scrolling table. --}}
                <div class="space-y-2.5 sm:hidden">
                    @foreach ($this->recentOrders as $order)
                        <div wire:key="recent-order-mobile-{{ $order->id }}" class="rounded-xl border border-emerald-100 bg-white p-3 dark:border-emerald-900/40 dark:bg-zinc-900">
                            <div class="flex items-center justify-between gap-2">
                                <a
                                    href="{{ route('admin.orders.show', $order) }}"
                                    wire:navigate
                                    class="truncate text-sm font-medium hover:underline"
                                    title="{{ $order->order_number }}"
                                >
                                    {{ $order->order_number }}
                                </a>
                                <x-admin.status-badge :status="$order->status" />
                            </div>

                            <div class="mt-1.5 flex items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="truncate" title="{{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}">
                                    {{ $order->user?->name ?? $order->guest_name ?? 'Guest' }}
                                </span>
                                <span class="shrink-0">{{ $order->placed_at?->format('d M, h:i A') }}</span>
                            </div>

                            <div class="mt-2 text-right text-sm font-semibold">
                                <x-customer.price :amount="$order->total" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Quick actions --}}
        <div>
            <flux:heading size="lg" class="mb-3">Quick Actions</flux:heading>

            <div class="grid grid-cols-1 gap-3 rounded-2xl border border-emerald-100 bg-white p-4 dark:border-emerald-900/40 dark:bg-zinc-900">
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
                        class="justify-start hover:bg-emerald-50 dark:hover:bg-emerald-900/20"
                        wire:key="quick-action-{{ $action['route'] }}"
                    >
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach
            </div>

            <div class="mt-4 rounded-2xl border border-emerald-100 bg-white p-4 dark:border-emerald-900/40 dark:bg-zinc-900">
                <flux:subheading>Menu Items</flux:subheading>
                <flux:heading size="lg" class="mt-1">{{ number_format($this->menuItems) }}</flux:heading>
            </div>
        </div>
    </div>
</div>
