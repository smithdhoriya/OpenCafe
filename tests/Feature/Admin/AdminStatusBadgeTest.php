<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

test('the orders list renders a distinct badge color for each order status', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-BADGE-001',
        'status' => OrderStatus::OutForDelivery,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Out for delivery')
        ->assertDontSee('color="orange"', false);
});

test('the order detail page renders the status badge for the current status', function () {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-BADGE-002',
        'status' => OrderStatus::Refunded,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Refunded');
});

test('every order status enum case maps to a renderable badge', function (OrderStatus $status) {
    $admin = User::factory()->admin()->create();

    $order = Order::factory()->for(User::factory())->create(['status' => $status]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee(ucfirst(str_replace('_', ' ', $status->value)));
})->with(OrderStatus::cases());
