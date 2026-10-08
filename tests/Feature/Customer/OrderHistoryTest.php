<?php

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;

test('guests are redirected to login when visiting the orders page', function () {
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});

test('an authenticated customer can view their orders', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'order_number' => 'ORD-20261006-TESTCASE',
        'type' => OrderType::Takeaway,
        'total' => 150,
    ]);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('ORD-20261006-TESTCASE')
        ->assertSee('Takeaway');
});

test('orders are listed newest first', function () {
    $user = User::factory()->create();
    $older = Order::factory()->for($user)->create([
        'order_number' => 'ORD-OLDER',
        'placed_at' => now()->subDays(2),
    ]);
    $newer = Order::factory()->for($user)->create([
        'order_number' => 'ORD-NEWER',
        'placed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSeeInOrder([$newer->order_number, $older->order_number]);
});

test('only the authenticated users orders are visible', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownOrder = Order::factory()->for($user)->create(['order_number' => 'ORD-MINE']);
    $otherOrder = Order::factory()->for($otherUser)->create(['order_number' => 'ORD-NOT-MINE']);

    $response = $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('ORD-MINE');

    $response->assertDontSee('ORD-NOT-MINE');
});

test('the empty state is shown when the customer has no orders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('No orders yet');
});

test('each order links to the existing order confirmation route', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee(route('orders.show', $order), escape: false);
});

test('another users order cannot be accessed through the linked detail page', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get(route('orders.show', $order))
        ->assertNotFound();
});
