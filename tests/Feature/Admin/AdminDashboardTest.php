<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\MenuItem;
use App\Models\Order;
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
