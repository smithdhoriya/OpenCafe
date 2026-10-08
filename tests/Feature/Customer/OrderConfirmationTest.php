<?php

use App\Enums\OrderType;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;

test('guests are redirected to login when viewing an order confirmation', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
});

test('a customer can view their own order confirmation', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['name' => 'Flat White']);
    $order = Order::factory()->for($user)->create([
        'order_number' => 'ORD-20261006-TESTCASE',
        'type' => OrderType::Takeaway,
        'subtotal' => 150,
        'delivery_charge' => 0,
        'total' => 150,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Flat White',
        'unit_price' => 150,
        'quantity' => 1,
        'line_total' => 150,
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('ORD-20261006-TESTCASE')
        ->assertSee('Flat White')
        ->assertSee('Pending')
        ->assertSeeText('unpaid');
});

test('a delivery order confirmation shows the address snapshot', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->delivery()->create([
        'subtotal' => 150,
        'delivery_charge' => 20,
        'total' => 170,
    ]);
    OrderDelivery::factory()->create([
        'order_id' => $order->id,
        'recipient_name' => 'Jane Doe',
        'address_line_1' => '221B Baker Street',
        'city' => 'Bengaluru',
        'distance_km' => 1.5,
        'delivery_charge' => 20,
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('221B Baker Street')
        ->assertSee('Bengaluru');
});

test('a customer cannot view another customers order', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('orders.show', $order))
        ->assertNotFound();
});
