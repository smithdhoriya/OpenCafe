<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

test('a customer cannot access the admin dashboard', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('a staff user cannot access the admin dashboard', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('a guest is redirected to login from the admin dashboard', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('the dashboard greets the authenticated admin by name', function () {
    $admin = User::factory()->admin()->create(['name' => 'Jordan Rivera']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Jordan Rivera');
});

test('the dashboard reports accurate order status counts', function () {
    $admin = User::factory()->admin()->create();

    Order::factory()->for(User::factory())->count(3)->create(['status' => OrderStatus::Pending]);
    Order::factory()->for(User::factory())->count(2)->create(['status' => OrderStatus::Preparing]);
    Order::factory()->for(User::factory())->create(['status' => OrderStatus::Completed]);
    Order::factory()->for(User::factory())->create(['status' => OrderStatus::Cancelled]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();

    $response->assertSeeTextInOrder(['Total Orders', '7']);

    foreach (OrderStatus::cases() as $status) {
        $response->assertSee(ucfirst(str_replace('_', ' ', $status->value)));
    }
});

test('the dashboard shows the latest orders with customer name and total', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create(['name' => 'Priya Nair']);

    $order = Order::factory()->for($customer)->create([
        'order_number' => 'ORD-DASH-001',
        'placed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Recent Orders')
        ->assertSee($order->order_number)
        ->assertSee('Priya Nair');
});

test('the dashboard shows an empty state when there are no orders', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('No orders have been placed yet.');
});

test('the dashboard calculates revenue from paid orders only', function () {
    $admin = User::factory()->admin()->create();

    Order::factory()->for(User::factory())->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 25,
    ]);
    Order::factory()->for(User::factory())->create([
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('₹25.00', false);
});

test('the dashboard lists quick actions linking to existing admin routes', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('Quick Actions');

    foreach ([
        route('admin.orders'),
        route('admin.menu'),
        route('admin.categories'),
        route('admin.customers'),
        route('admin.offers'),
        route('admin.reviews'),
        route('admin.settings'),
    ] as $url) {
        $response->assertSee($url, false);
    }
});

test('the dashboard reports real menu item and customer counts', function () {
    $admin = User::factory()->admin()->create();
    MenuItem::factory()->count(4)->create();
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSeeTextInOrder(['Menu Items', '4']);
});

test('the business analytics panel shows honest empty states when there is no data', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Business Analytics')
        ->assertSee('No paid sales recorded in the last 30 days.')
        ->assertSee('No orders recorded yet.')
        ->assertSee('No sales recorded in the last 30 days to rank menu items.');
});

test('the business analytics sales trend shows the real total paid revenue for the last 30 days', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 123.45,
        'placed_at' => now()->subDays(2),
    ]);
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 999,
        'placed_at' => now()->subDays(1),
    ]);
    // Outside the 30-day window — must not be counted in the chart total.
    Order::factory()->for($customer)->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 500,
        'placed_at' => now()->subDays(40),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('₹123.45 total', false)
        ->assertDontSee('₹623.45 total', false);
});

test('the business analytics order status chart shows real per-status counts', function () {
    $admin = User::factory()->admin()->create();

    Order::factory()->for(User::factory())->count(2)->create(['status' => OrderStatus::Preparing]);
    Order::factory()->for(User::factory())->create(['status' => OrderStatus::Cancelled]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSeeTextInOrder(['Preparing', '2']);
    $response->assertSeeTextInOrder(['Cancelled', '1']);
});

test('the business analytics best-sellers chart ranks real menu items by quantity sold, excluding cancelled orders', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create();
    $bestSeller = MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Top Seller Latte']);
    $cancelledOnly = MenuItem::factory()->create(['menu_category_id' => $category->id, 'name' => 'Only Cancelled Item']);

    $order = Order::factory()->create(['status' => OrderStatus::Completed, 'placed_at' => now()]);
    OrderItem::factory()->for($order)->create([
        'menu_item_id' => $bestSeller->id,
        'item_name' => $bestSeller->name,
        'quantity' => 7,
        'unit_price' => 10,
        'line_total' => 70,
    ]);

    $cancelledOrder = Order::factory()->create(['status' => OrderStatus::Cancelled, 'placed_at' => now()]);
    OrderItem::factory()->for($cancelledOrder)->create([
        'menu_item_id' => $cancelledOnly->id,
        'item_name' => $cancelledOnly->name,
        'quantity' => 9,
        'unit_price' => 10,
        'line_total' => 90,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSeeTextInOrder(['Top Seller Latte', '7 sold']);
});
