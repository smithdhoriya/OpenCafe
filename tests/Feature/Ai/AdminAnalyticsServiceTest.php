<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SettingType;
use App\Enums\UserRole;
use App\Models\CafeSetting;
use App\Models\Combo;
use App\Models\DeliveryRate;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Ai\AdminAnalyticsService;

beforeEach(function () {
    $this->analytics = new AdminAnalyticsService;
});

test('sales summary counts only paid orders toward revenue, matching the admin dashboard rule', function () {
    $customer = User::factory()->create();

    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'status' => OrderStatus::Completed,
        'total' => 40,
        'placed_at' => now(),
    ]);
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Unpaid,
        'status' => OrderStatus::Pending,
        'total' => 100,
        'placed_at' => now(),
    ]);
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'status' => OrderStatus::Cancelled,
        'total' => 999,
        'placed_at' => now(),
    ]);

    $summary = $this->analytics->salesSummary('Today', now()->startOfDay(), now()->endOfDay());

    expect($summary['orders_placed'])->toBe(3)
        ->and($summary['paid_orders'])->toBe(2)
        ->and($summary['revenue'])->toBe(1039.0)
        ->and($summary['orders_cancelled'])->toBe(1);
});

test('average order value divides revenue by the number of paid orders', function () {
    $customer = User::factory()->create();

    Order::factory()->for($customer)->create(['payment_status' => PaymentStatus::Paid, 'total' => 30, 'placed_at' => now()]);
    Order::factory()->for($customer)->create(['payment_status' => PaymentStatus::Paid, 'total' => 50, 'placed_at' => now()]);

    $summary = $this->analytics->salesSummary('Today', now()->startOfDay(), now()->endOfDay());

    expect($summary['average_order_value'])->toBe(40.0);
});

test('sales summary handles an empty dataset safely', function () {
    $summary = $this->analytics->salesSummary('Today', now()->startOfDay(), now()->endOfDay());

    expect($summary['orders_placed'])->toBe(0)
        ->and($summary['revenue'])->toBe(0.0)
        ->and($summary['average_order_value'])->toBe(0.0);
});

test('an inverted date range is normalized instead of erroring', function () {
    $customer = User::factory()->create();
    Order::factory()->for($customer)->create(['payment_status' => PaymentStatus::Paid, 'total' => 20, 'placed_at' => now()]);

    $summary = $this->analytics->salesSummary('Test', now()->endOfDay(), now()->startOfDay());

    expect($summary['orders_placed'])->toBe(1);
});

test('order status breakdown reports real counts per status including zero-count statuses', function () {
    $customer = User::factory()->create();
    Order::factory()->for($customer)->count(3)->create(['status' => OrderStatus::Pending]);
    Order::factory()->for($customer)->create(['status' => OrderStatus::Completed]);

    $breakdown = $this->analytics->orderStatusBreakdown();

    expect($breakdown['pending'])->toBe(3)
        ->and($breakdown['completed'])->toBe(1)
        ->and($breakdown['refunded'])->toBe(0);
});

test('orders needing attention flags only non-terminal orders older than the stated threshold', function () {
    $customer = User::factory()->create();

    $stale = Order::factory()->for($customer)->create([
        'status' => OrderStatus::Pending,
        'placed_at' => now()->subHours(2),
    ]);
    Order::factory()->for($customer)->create([
        'status' => OrderStatus::Pending,
        'placed_at' => now(),
    ]);
    Order::factory()->for($customer)->create([
        'status' => OrderStatus::Completed,
        'placed_at' => now()->subHours(5),
    ]);

    $attention = $this->analytics->ordersNeedingAttention(staleAfterMinutes: 30);

    expect($attention['stale_after_minutes'])->toBe(30)
        ->and($attention['active_count'])->toBe(2)
        ->and($attention['stale_orders'])->toHaveCount(1)
        ->and($attention['stale_orders'][0]['order_number'])->toBe($stale->order_number)
        ->and($attention['stale_orders'][0]['age_minutes'])->toBeGreaterThanOrEqual(119);
});

test('recent orders are limited and ordered by most recently placed', function () {
    $customer = User::factory()->create();

    Order::factory()->for($customer)->create(['order_number' => 'ORD-OLD', 'placed_at' => now()->subDays(2)]);
    $recent = Order::factory()->for($customer)->create(['order_number' => 'ORD-NEW', 'placed_at' => now()]);

    $orders = $this->analytics->recentOrders(limit: 1);

    expect($orders)->toHaveCount(1)
        ->and($orders[0]['order_number'])->toBe($recent->order_number);
});

test('top selling items are ranked by quantity sold, excluding cancelled orders', function () {
    $category = MenuCategory::factory()->create();
    $popular = MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Popular Latte']);
    $cancelledOnly = MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Cancelled Mocha']);

    $order = Order::factory()->create(['placed_at' => now(), 'status' => OrderStatus::Completed]);
    OrderItem::factory()->for($order)->create(['menu_item_id' => $popular->id, 'quantity' => 5, 'unit_price' => 10, 'line_total' => 50]);

    $cancelledOrder = Order::factory()->create(['placed_at' => now(), 'status' => OrderStatus::Cancelled]);
    OrderItem::factory()->for($cancelledOrder)->create(['menu_item_id' => $cancelledOnly->id, 'quantity' => 9, 'unit_price' => 10, 'line_total' => 90]);

    $window = $this->analytics->standardWindows()['last_30_days'];
    $top = $this->analytics->topSellingItems($window['from'], $window['to']);

    $names = array_column($top, 'name');

    expect($names)->toContain('Popular Latte')
        ->and($top[array_search('Popular Latte', $names)]['quantity_sold'])->toBe(5);

    $cancelledRow = collect($top)->firstWhere('name', 'Cancelled Mocha');
    expect($cancelledRow['quantity_sold'] ?? 0)->toBe(0);
});

test('low selling items include available items with zero sales in the window', function () {
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Never Ordered Item', 'is_available' => true]);
    MenuItem::factory()->unavailable()->create(['menu_category_id' => $category->id, 'name' => 'Unavailable Item']);

    $window = $this->analytics->standardWindows()['last_30_days'];
    $low = $this->analytics->lowSellingItems($window['from'], $window['to']);

    $names = array_column($low, 'name');

    expect($names)->toContain('Never Ordered Item')
        ->and($names)->not->toContain('Unavailable Item');
});

test('category sales aggregate quantity and revenue per menu category', function () {
    $category = MenuCategory::factory()->create(['name' => 'Beverages']);
    $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $order = Order::factory()->create(['placed_at' => now(), 'status' => OrderStatus::Completed]);
    OrderItem::factory()->for($order)->create(['menu_item_id' => $item->id, 'item_name' => $item->name, 'quantity' => 3, 'unit_price' => 20, 'line_total' => 60]);

    $window = $this->analytics->standardWindows()['last_30_days'];
    $sales = $this->analytics->categorySales($window['from'], $window['to']);

    expect($sales)->toHaveCount(1)
        ->and($sales[0]['name'])->toBe('Beverages')
        ->and($sales[0]['quantity_sold'])->toBe(3)
        ->and($sales[0]['revenue'])->toBe(60.0);
});

test('combo performance aggregates quantity and revenue per combo from recorded order lines', function () {
    $combo = Combo::factory()->create(['name' => 'Breakfast Combo']);
    $order = Order::factory()->create(['placed_at' => now(), 'status' => OrderStatus::Completed]);
    OrderItem::factory()->for($order)->forCombo()->create(['combo_id' => $combo->id, 'item_name' => $combo->name, 'quantity' => 2, 'unit_price' => 15, 'line_total' => 30]);

    $window = $this->analytics->standardWindows()['last_30_days'];
    $performance = $this->analytics->comboPerformance($window['from'], $window['to']);

    expect($performance)->toHaveCount(1)
        ->and($performance[0]['name'])->toBe('Breakfast Combo')
        ->and($performance[0]['quantity_sold'])->toBe(2)
        ->and($performance[0]['revenue'])->toBe(30.0);
});

test('customer stats count new accounts and distinguish repeat customers from one-time orderers', function () {
    $window = $this->analytics->standardWindows()['last_30_days'];

    $repeatCustomer = User::factory()->create(['role' => UserRole::Customer, 'created_at' => now()->subDays(5)]);
    $oneTimeCustomer = User::factory()->create(['role' => UserRole::Customer, 'created_at' => now()->subDays(3)]);

    Order::factory()->for($repeatCustomer)->count(2)->create(['placed_at' => now()]);
    Order::factory()->for($oneTimeCustomer)->create(['placed_at' => now()]);

    $stats = $this->analytics->customerStats($window['label'], $window['from'], $window['to']);

    expect($stats['new_customers'])->toBe(2)
        ->and($stats['ordering_customers'])->toBe(2)
        ->and($stats['repeat_customers'])->toBe(1);
});

test('active offers report only currently active offers with real, recorded usage', function () {
    $active = Offer::factory()->percentage()->create(['code' => 'ACTIVE10', 'value' => 10]);
    Offer::factory()->expired()->create(['code' => 'EXPIRED']);
    Offer::factory()->inactive()->create(['code' => 'DISABLED']);

    $order = Order::factory()->create([
        'offer_id' => $active->id,
        'status' => OrderStatus::Completed,
        'discount_total' => 15,
        'placed_at' => now(),
    ]);

    $offers = $this->analytics->activeOffers();

    expect($offers)->toHaveCount(1)
        ->and($offers[0]['code'])->toBe('ACTIVE10')
        ->and($offers[0]['orders_using_offer_recorded'])->toBe(1)
        ->and($offers[0]['total_discount_recorded'])->toBe(15.0);
});

test('a cancelled order using an offer is excluded from recorded discount totals', function () {
    $offer = Offer::factory()->percentage()->create(['code' => 'SAVE5']);

    Order::factory()->create([
        'offer_id' => $offer->id,
        'status' => OrderStatus::Cancelled,
        'discount_total' => 50,
        'placed_at' => now(),
    ]);

    $offers = $this->analytics->activeOffers();

    expect($offers[0]['orders_using_offer_recorded'])->toBe(0)
        ->and($offers[0]['total_discount_recorded'])->toBe(0.0);
});

test('delivery settings reflect real cafe settings and delivery rates, not invented values', function () {
    CafeSetting::query()->create(['key' => 'delivery_enabled', 'value' => '1', 'type' => SettingType::Boolean]);
    CafeSetting::query()->create(['key' => 'min_order_for_delivery', 'value' => '99.00', 'type' => SettingType::Decimal]);
    DeliveryRate::factory()->create(['min_distance_km' => 0, 'max_distance_km' => 3, 'charge' => 25, 'is_active' => true]);

    $settings = $this->analytics->deliverySettings();

    expect($settings['delivery_enabled'])->toBeTrue()
        ->and($settings['min_order_for_delivery'])->toBe(99.0)
        ->and($settings['delivery_rates'])->toHaveCount(1)
        ->and($settings['delivery_rates'][0]['charge'])->toBe(25.0);
});

test('cafe settings omit anything not actually configured', function () {
    $settings = $this->analytics->cafeSettings();

    expect($settings['name'])->toBeNull()
        ->and($settings['address'])->toBeNull();
});

test('daily sales trend returns one zero-filled entry per day in the range, even with no orders', function () {
    $from = now()->subDays(2)->startOfDay();
    $to = now()->startOfDay();

    $trend = $this->analytics->dailySalesTrend($from, $to);

    expect($trend)->toHaveCount(3)
        ->and(collect($trend)->pluck('revenue')->unique()->all())->toBe([0.0])
        ->and(collect($trend)->pluck('orders_placed')->unique()->all())->toBe([0]);

    expect($trend[0]['date'])->toBe($from->toDateString())
        ->and($trend[2]['date'])->toBe($to->toDateString());
});

test('daily sales trend attributes paid revenue to the correct calendar day and ignores unpaid orders', function () {
    $customer = User::factory()->create();
    $today = now()->startOfDay();
    $yesterday = $today->copy()->subDay();

    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 30,
        'placed_at' => $yesterday->copy()->addHours(10),
    ]);
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 20,
        'placed_at' => $yesterday->copy()->addHours(14),
    ]);
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 500,
        'placed_at' => $yesterday->copy()->addHours(12),
    ]);
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 15,
        'placed_at' => $today->copy()->addHours(9),
    ]);

    $trend = collect($this->analytics->dailySalesTrend($yesterday, $today->copy()->endOfDay()))->keyBy('date');

    expect($trend[$yesterday->toDateString()]['revenue'])->toBe(50.0)
        ->and($trend[$yesterday->toDateString()]['orders_placed'])->toBe(2)
        ->and($trend[$today->toDateString()]['revenue'])->toBe(15.0)
        ->and($trend[$today->toDateString()]['orders_placed'])->toBe(1);
});

test('daily sales trend normalizes an inverted date range instead of returning nothing', function () {
    $customer = User::factory()->create();
    $today = now()->startOfDay();

    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 10,
        'placed_at' => $today,
    ]);

    $trend = $this->analytics->dailySalesTrend($today, $today->copy()->subDays(2));

    expect($trend)->toHaveCount(3);
});
