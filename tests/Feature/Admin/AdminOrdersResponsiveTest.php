<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Livewire\Volt\Volt;

test('the admin layout lets its main content area shrink instead of forcing the whole page to overflow', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('min-w-0', false);
});

test('the orders list renders both a mobile card layout and a desktop table for the same orders', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['order_number' => 'ORD-RESPONSIVE-001']);

    $response = $this->actingAs($admin)->get(route('admin.orders'));

    $response->assertOk();
    $response->assertSee('lg:hidden', false);
    $response->assertSee('hidden overflow-x-auto rounded-2xl', false);
    $response->assertSeeInOrder(['ORD-RESPONSIVE-001', 'ORD-RESPONSIVE-001']);
});

test('the orders list table pins its actions column to the scroll container, not the page', function () {
    $admin = User::factory()->admin()->create();
    Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    $response = $this->actingAs($admin)->get(route('admin.orders'));

    $response->assertOk();
    $response->assertSee('sticky right-0', false);
});

test('status action buttons stay reachable in both the card and table layouts', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-RESPONSIVE-002',
        'status' => OrderStatus::Pending,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.orders'));

    $response->assertOk();
    // Rendered once in the mobile card and once in the desktop table.
    $response->assertSee('Mark Confirmed');
    $response->assertSee('ORD-RESPONSIVE-002');
    expect(substr_count($response->getContent(), 'Mark Confirmed'))->toBeGreaterThanOrEqual(2);
});

test('the orders list no longer renders separate subtotal and delivery columns on the table', function () {
    $admin = User::factory()->admin()->create();
    Order::factory()->for(User::factory())->create();

    $this->actingAs($admin)
        ->get(route('admin.orders'))
        ->assertOk()
        ->assertDontSee('>Subtotal</th>', false)
        ->assertDontSee('>Delivery</th>', false);
});

test('advancing status from the orders list still works after the responsive markup change', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->call('advanceStatus', $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

test('the order detail page keeps its item table inside its own contained scroll region', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create();

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('overflow-x-auto', false);
});
