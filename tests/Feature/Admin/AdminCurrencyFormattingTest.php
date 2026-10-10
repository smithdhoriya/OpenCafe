<?php

use App\Enums\PaymentStatus;
use App\Enums\SettingType;
use App\Models\CafeSetting;
use App\Models\Order;
use App\Models\User;

test('the dashboard shows total revenue in rupees, not dollars', function () {
    $admin = User::factory()->admin()->create();

    Order::factory()->for(User::factory())->create([
        'payment_status' => PaymentStatus::Paid,
        'total' => 1234.50,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('₹1,234.50', false);
    $response->assertDontSee('$1,234.50', false);
});

test('the orders list shows amounts in rupees, not dollars', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-CURRENCY-001',
        'subtotal' => 500,
        'delivery_charge' => 25,
        'total' => 525,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.orders'));

    $response->assertOk();
    $response->assertSee('₹525.00', false);
    $response->assertDontSee('$525.00', false);
});

test('the order detail page shows amounts in rupees, not dollars', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-CURRENCY-002',
        'subtotal' => 800,
        'delivery_charge' => 0,
        'total' => 800,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

    $response->assertOk();
    $response->assertSee('₹800.00', false);
    $response->assertDontSee('$800.00', false);
});

test('the currency symbol follows the configured cafe currency setting', function () {
    CafeSetting::query()->updateOrCreate(
        ['key' => 'currency'],
        ['value' => 'USD', 'type' => SettingType::String, 'group' => 'general', 'label' => 'Currency'],
    );

    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-CURRENCY-003',
        'total' => 50,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('$50.00', false);
});
