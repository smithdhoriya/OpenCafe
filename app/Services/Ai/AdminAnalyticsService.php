<?php

namespace App\Services\Ai;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CafeSetting;
use App\Models\DeliveryRate;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dedicated, read-only analytics for the Admin AI assistant.
 *
 * Every method here returns small, pre-aggregated arrays built with the
 * query builder/Eloquent — never raw models or unrestricted result sets.
 * The assistant (and therefore the language model) only ever sees what is
 * returned here; it has no query or tool-calling access of its own. This
 * is the single source of truth the Admin AI is grounded in, so it cannot
 * invent sales, orders, or customer figures.
 *
 * Statuses that are excluded from "sales" everywhere below (Cancelled,
 * Refunded) and the Paid-only revenue rule mirror the admin dashboard
 * (resources/views/pages/admin/dashboard.blade.php), so the assistant's
 * numbers never disagree with the dashboard the admin already trusts.
 */
class AdminAnalyticsService
{
    private const RECENT_ORDERS_LIMIT = 10;

    private const ATTENTION_ORDERS_LIMIT = 10;

    private const ITEM_LIMIT = 10;

    /**
     * Order statuses that represent a sale actually completing or still in
     * flight — excludes Cancelled and Refunded, which never count as sales.
     */
    private const NON_SALE_STATUSES = [OrderStatus::Cancelled, OrderStatus::Refunded];

    /**
     * Fixed, deterministic reporting windows. The assistant only ever
     * reports figures for one of these — it cannot compute an arbitrary
     * custom range, since no date-parsing or tool-calling is exposed to it.
     *
     * @return array<string, array{label: string, from: Carbon, to: Carbon}>
     */
    public function standardWindows(): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        return [
            'today' => [
                'label' => 'Today ('.$todayStart->toDateString().')',
                'from' => $todayStart,
                'to' => $todayEnd,
            ],
            'yesterday' => [
                'label' => 'Yesterday ('.$todayStart->copy()->subDay()->toDateString().')',
                'from' => $todayStart->copy()->subDay(),
                'to' => $todayStart->copy()->subSecond(),
            ],
            'this_week' => [
                'label' => 'This week ('.$now->copy()->startOfWeek()->toDateString().' to '.$todayStart->toDateString().')',
                'from' => $now->copy()->startOfWeek(),
                'to' => $todayEnd,
            ],
            'last_week' => [
                'label' => 'Last week ('.$now->copy()->subWeek()->startOfWeek()->toDateString().' to '.$now->copy()->subWeek()->endOfWeek()->toDateString().')',
                'from' => $now->copy()->subWeek()->startOfWeek(),
                'to' => $now->copy()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                'label' => 'This month ('.$now->copy()->startOfMonth()->toDateString().' to '.$todayStart->toDateString().')',
                'from' => $now->copy()->startOfMonth(),
                'to' => $todayEnd,
            ],
            'last_month' => [
                'label' => 'Last month ('.$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString().' to '.$now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString().')',
                'from' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'to' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'last_30_days' => [
                'label' => 'Last 30 days ('.$now->copy()->subDays(29)->toDateString().' to '.$todayStart->toDateString().')',
                'from' => $now->copy()->subDays(29)->startOfDay(),
                'to' => $todayEnd,
            ],
        ];
    }

    /**
     * Sales and order counts for a window, using the application's own
     * revenue rule: only Paid orders contribute to revenue, never
     * Cancelled/Refunded ones.
     *
     * @return array{label: string, from: string, to: string, orders_placed: int, orders_completed: int, orders_cancelled: int, orders_refunded: int, paid_orders: int, revenue: float, average_order_value: float}
     */
    public function salesSummary(string $label, Carbon $from, Carbon $to): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $base = Order::query()->whereBetween('placed_at', [$from, $to]);

        $statusCounts = $base->clone()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $paidOrders = $base->clone()->where('payment_status', PaymentStatus::Paid);
        $paidOrderCount = $paidOrders->clone()->count();
        $revenue = (float) $paidOrders->clone()->sum('total');

        return [
            'label' => $label,
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'orders_placed' => (int) $base->clone()->count(),
            'orders_completed' => (int) ($statusCounts[OrderStatus::Completed->value] ?? 0),
            'orders_cancelled' => (int) ($statusCounts[OrderStatus::Cancelled->value] ?? 0),
            'orders_refunded' => (int) ($statusCounts[OrderStatus::Refunded->value] ?? 0),
            'paid_orders' => $paidOrderCount,
            'revenue' => round($revenue, 2),
            'average_order_value' => $paidOrderCount > 0 ? round($revenue / $paidOrderCount, 2) : 0.0,
        ];
    }

    /**
     * salesSummary() for every fixed reporting window, so the assistant can
     * compare periods (e.g. today vs yesterday, this month vs last month)
     * without ever computing a date range itself.
     *
     * @return array<string, array>
     */
    public function allStandardSalesSummaries(): array
    {
        return collect($this->standardWindows())
            ->map(fn (array $window, string $key) => $this->salesSummary($window['label'], $window['from'], $window['to']))
            ->all();
    }

    /**
     * All-time order counts per status — the same figures the admin
     * dashboard shows, so the assistant never contradicts it.
     *
     * @return array<string, int>
     */
    public function orderStatusBreakdown(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn (OrderStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
            ->all();
    }

    /**
     * Non-terminal orders (not Completed/Cancelled/Refunded), flagging the
     * ones open longer than $staleAfterMinutes as needing attention. The
     * threshold is returned alongside the data so the assistant must state
     * it rather than present it as an absolute rule — there is no
     * application-defined SLA for order handling time.
     *
     * @return array{stale_after_minutes: int, active_count: int, stale_orders: list<array>}
     */
    public function ordersNeedingAttention(int $staleAfterMinutes = 30): array
    {
        $active = Order::query()->whereNotIn('status', [
            OrderStatus::Completed,
            ...self::NON_SALE_STATUSES,
        ]);

        $activeCount = $active->clone()->count();

        $staleOrders = $active->clone()
            ->with('user')
            ->where('placed_at', '<=', now()->subMinutes($staleAfterMinutes))
            ->orderBy('placed_at')
            ->take(self::ATTENTION_ORDERS_LIMIT)
            ->get()
            ->map(fn (Order $order) => $this->summarizeOrder($order, withAgeMinutes: true))
            ->all();

        return [
            'stale_after_minutes' => $staleAfterMinutes,
            'active_count' => $activeCount,
            'stale_orders' => $staleOrders,
        ];
    }

    /**
     * Most recently placed orders, minimal fields only.
     *
     * @return list<array>
     */
    public function recentOrders(int $limit = self::RECENT_ORDERS_LIMIT): array
    {
        return Order::query()
            ->with('user')
            ->latest('placed_at')
            ->take(max(1, min($limit, 50)))
            ->get()
            ->map(fn (Order $order) => $this->summarizeOrder($order))
            ->all();
    }

    /**
     * Best-selling menu items by quantity within a window, excluding
     * cancelled/refunded orders. Combo lines are excluded here — see
     * comboPerformance().
     *
     * @return list<array{menu_item_id: int, name: string, quantity_sold: int, revenue: float}>
     */
    public function topSellingItems(Carbon $from, Carbon $to, int $limit = self::ITEM_LIMIT): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        return $this->itemSalesQuery($from, $to)
            ->orderByDesc('quantity_sold')
            ->orderBy('menu_items.name')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn ($row) => [
                'menu_item_id' => (int) $row->id,
                'name' => $row->name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Available menu items with the lowest sales volume in a window,
     * including items with zero sales. No invented "low-performing"
     * threshold is applied — the caller gets the bottom N by quantity sold
     * for the exact window given, and must explain that method rather than
     * a fabricated cutoff.
     *
     * @return list<array{menu_item_id: int, name: string, quantity_sold: int, revenue: float}>
     */
    public function lowSellingItems(Carbon $from, Carbon $to, int $limit = self::ITEM_LIMIT): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        return $this->itemSalesQuery($from, $to, onlyAvailable: true)
            ->orderBy('quantity_sold')
            ->orderBy('menu_items.name')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn ($row) => [
                'menu_item_id' => (int) $row->id,
                'name' => $row->name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Revenue and quantity sold per active menu category within a window.
     *
     * @return list<array{category_id: int, name: string, quantity_sold: int, revenue: float}>
     */
    public function categorySales(Carbon $from, Carbon $to, int $limit = self::ITEM_LIMIT): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereNotIn('orders.status', $this->nonSaleStatusValues())
            ->selectRaw('menu_categories.id as id, menu_categories.name as name, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total) as revenue')
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->orderByDesc('revenue')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn ($row) => [
                'category_id' => (int) $row->id,
                'name' => $row->name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * Combo performance within a window — quantity and revenue per combo,
     * from recorded order lines only.
     *
     * @return list<array{combo_id: int, name: string, quantity_sold: int, revenue: float}>
     */
    public function comboPerformance(Carbon $from, Carbon $to, int $limit = self::ITEM_LIMIT): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('combos', 'combos.id', '=', 'order_items.combo_id')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->whereNotIn('orders.status', $this->nonSaleStatusValues())
            ->selectRaw('combos.id as id, combos.name as name, SUM(order_items.quantity) as quantity_sold, SUM(order_items.line_total) as revenue')
            ->groupBy('combos.id', 'combos.name')
            ->orderByDesc('revenue')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn ($row) => [
                'combo_id' => (int) $row->id,
                'name' => $row->name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * New vs. repeat customer counts for a window. A "repeat customer" is
     * one who has more than one order all-time, among those who ordered
     * within the window — no PII beyond a count is ever returned.
     *
     * @return array{label: string, new_customers: int, ordering_customers: int, repeat_customers: int}
     */
    public function customerStats(string $label, Carbon $from, Carbon $to): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $newCustomers = User::query()
            ->where('role', UserRole::Customer)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $activeCustomerIds = Order::query()
            ->whereBetween('placed_at', [$from, $to])
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $orderingCustomers = $activeCustomerIds->count();

        $repeatCustomers = $orderingCustomers === 0 ? 0 : Order::query()
            ->whereIn('user_id', $activeCustomerIds)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        return [
            'label' => $label,
            'new_customers' => $newCustomers,
            'ordering_customers' => $orderingCustomers,
            'repeat_customers' => $repeatCustomers,
        ];
    }

    /**
     * Currently active offers with their real recorded usage. Discount
     * totals come from orders.discount_total on orders that actually used
     * the offer — never claimed unless a real transaction recorded it.
     *
     * @return list<array>
     */
    public function activeOffers(): array
    {
        $offers = Offer::query()->where('is_active', true)->get()->filter(fn (Offer $offer) => $offer->isCurrentlyActive());

        if ($offers->isEmpty()) {
            return [];
        }

        $discountTotals = Order::query()
            ->whereIn('offer_id', $offers->pluck('id'))
            ->whereNotIn('status', $this->nonSaleStatusValues())
            ->selectRaw('offer_id, SUM(discount_total) as total_discount, COUNT(*) as uses_recorded')
            ->groupBy('offer_id')
            ->get()
            ->keyBy('offer_id');

        return $offers->map(function (Offer $offer) use ($discountTotals) {
            $recorded = $discountTotals->get($offer->id);

            return [
                'code' => $offer->code,
                'name' => $offer->name,
                'type' => $offer->type->value,
                'value' => (float) $offer->value,
                'min_order_total' => $offer->min_order_total !== null ? (float) $offer->min_order_total : null,
                'max_discount_amount' => $offer->max_discount_amount !== null ? (float) $offer->max_discount_amount : null,
                'starts_at' => $offer->starts_at?->toDateTimeString(),
                'ends_at' => $offer->ends_at?->toDateTimeString(),
                'usage_limit' => $offer->usage_limit,
                'usage_limit_per_user' => $offer->usage_limit_per_user,
                'times_used_recorded' => (int) $offer->times_used,
                'orders_using_offer_recorded' => (int) ($recorded?->uses_recorded ?? 0),
                'total_discount_recorded' => round((float) ($recorded?->total_discount ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * Delivery/dine-in/takeaway configuration, sourced from the same
     * CafeSetting/DeliveryRate records the checkout page reads.
     */
    public function deliverySettings(): array
    {
        $rates = DeliveryRate::query()
            ->where('is_active', true)
            ->orderBy('min_distance_km')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DeliveryRate $rate) => [
                'min_distance_km' => (float) $rate->min_distance_km,
                'max_distance_km' => $rate->max_distance_km !== null ? (float) $rate->max_distance_km : null,
                'charge' => (float) $rate->charge,
            ])
            ->all();

        return [
            'dine_in_enabled' => (bool) CafeSetting::get('dine_in_enabled', false),
            'takeaway_enabled' => (bool) CafeSetting::get('takeaway_enabled', false),
            'delivery_enabled' => (bool) CafeSetting::get('delivery_enabled', false),
            'min_order_for_delivery' => (float) CafeSetting::get('min_order_for_delivery', 0),
            'delivery_rates' => $rates,
        ];
    }

    /**
     * Cafe identity, contact, and opening hours — only what is actually
     * configured, nothing invented.
     */
    public function cafeSettings(): array
    {
        return [
            'name' => CafeSetting::get('cafe_name'),
            'tagline' => CafeSetting::get('cafe_tagline'),
            'address' => CafeSetting::get('address'),
            'contact_email' => CafeSetting::get('contact_email'),
            'contact_phone' => CafeSetting::get('contact_phone'),
            'opening_hours' => CafeSetting::get('opening_hours'),
            'currency' => CafeSetting::get('currency', 'INR'),
        ];
    }

    /**
     * @return array{id: int, order_number: string, status: string, type: string, customer: string, placed_at: ?string, total: float, age_minutes?: int}
     */
    private function summarizeOrder(Order $order, bool $withAgeMinutes = false): array
    {
        $summary = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'type' => $order->type->value,
            'customer' => $order->user?->name ?? $order->guest_name ?? 'Guest',
            'placed_at' => $order->placed_at?->toDateTimeString(),
            'total' => (float) $order->total,
        ];

        if ($withAgeMinutes && $order->placed_at !== null) {
            $summary['age_minutes'] = (int) $order->placed_at->diffInMinutes(now());
        }

        return $summary;
    }

    /**
     * Shared base query for per-menu-item quantity/revenue within a window,
     * built as a single join so there is no N+1 and no unbounded fetch.
     */
    private function itemSalesQuery(Carbon $from, Carbon $to, bool $onlyAvailable = false)
    {
        $query = DB::table('menu_items')
            ->leftJoin('order_items', function ($join) use ($from, $to) {
                $join->on('order_items.menu_item_id', '=', 'menu_items.id')
                    ->whereIn('order_items.order_id', function ($sub) use ($from, $to) {
                        $sub->select('id')
                            ->from('orders')
                            ->whereBetween('placed_at', [$from, $to])
                            ->whereNotIn('status', $this->nonSaleStatusValues());
                    });
            })
            ->whereNull('menu_items.deleted_at')
            ->selectRaw('menu_items.id as id, menu_items.name as name, COALESCE(SUM(order_items.quantity), 0) as quantity_sold, COALESCE(SUM(order_items.line_total), 0) as revenue')
            ->groupBy('menu_items.id', 'menu_items.name');

        if ($onlyAvailable) {
            $query->where('menu_items.is_available', true);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function nonSaleStatusValues(): array
    {
        return array_map(fn (OrderStatus $status) => $status->value, self::NON_SALE_STATUSES);
    }

    /**
     * Guards against an inverted range (e.g. a "to" date before "from")
     * instead of returning a nonsensical empty window — swap rather than
     * error, since this feeds a read-only report, not a form validator.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function normalizeRange(Carbon $from, Carbon $to): array
    {
        return $from->lte($to) ? [$from, $to] : [$to, $from];
    }
}
