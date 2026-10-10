<?php

use App\Actions\Orders\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

test('an admin can advance a pending order to confirmed', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Confirmed);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Confirmed);
    expect($order->confirmed_at)->not->toBeNull();
});

test('an admin can walk a takeaway order through its full lifecycle', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'type' => OrderType::Takeaway,
        'status' => OrderStatus::Pending,
    ]);
    $action = app(UpdateOrderStatus::class);

    foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $step) {
        $action->handle($admin, $order->id, $step);
        $order->refresh();
        expect($order->status)->toBe($step);
    }
});

test('a delivery order must pass through out for delivery before completion', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->delivery()->create(['status' => OrderStatus::Ready]);
    $action = app(UpdateOrderStatus::class);

    expect(fn () => $action->handle($admin, $order->id, OrderStatus::Completed))
        ->toThrow(ValidationException::class);

    $action->handle($admin, $order->id, OrderStatus::OutForDelivery);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::OutForDelivery);

    $action->handle($admin, $order->id, OrderStatus::Completed);
    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);
});

test('out for delivery is not offered for a takeaway order', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'type' => OrderType::Takeaway,
        'status' => OrderStatus::Ready,
    ]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::OutForDelivery))
        ->toThrow(ValidationException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

test('out for delivery is not offered for a dine-in order', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'type' => OrderType::DineIn,
        'status' => OrderStatus::Ready,
    ]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::OutForDelivery))
        ->toThrow(ValidationException::class);
});

test('an admin can cancel a pending, confirmed, or preparing order', function (OrderStatus $status) {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => $status]);

    app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Cancelled);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelled_at)->not->toBeNull();
    expect($order->cancellation_reason)->toContain($admin->name);
})->with([
    'pending' => OrderStatus::Pending,
    'confirmed' => OrderStatus::Confirmed,
    'preparing' => OrderStatus::Preparing,
]);

test('an order cannot be cancelled once it is ready, out for delivery, or completed', function (OrderStatus $status) {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->delivery()->create(['status' => $status]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Cancelled))
        ->toThrow(ValidationException::class);

    expect($order->refresh()->status)->toBe($status);
})->with([
    'ready' => OrderStatus::Ready,
    'out for delivery' => OrderStatus::OutForDelivery,
    'completed' => OrderStatus::Completed,
]);

test('a paid completed order can be marked refunded', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
    ]);

    app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Refunded);

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded);
});

test('an unpaid order cannot be marked refunded', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Refunded))
        ->toThrow(ValidationException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

test('marking an order refunded does not change its payment amounts', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 123.45,
    ]);

    app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Refunded);

    $order->refresh();
    expect((float) $order->total)->toBe(123.45);
    expect($order->payment_status)->toBe(PaymentStatus::Paid);
});

test('refunded is a terminal status with no further transitions', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Refunded]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Completed))
        ->toThrow(ValidationException::class);
});

test('a status cannot skip stages in the lifecycle', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Preparing))
        ->toThrow(ValidationException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

test('a double submission cannot apply the same transition twice', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);
    $action = app(UpdateOrderStatus::class);

    $action->handle($admin, $order->id, OrderStatus::Confirmed);

    expect(fn () => $action->handle($admin, $order->id, OrderStatus::Confirmed))
        ->toThrow(ValidationException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a customer cannot reach the admin order detail page to update a status', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    $this->actingAs($customer)
        ->get(route('admin.orders.show', $order))
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

test('a guest cannot reach the admin status-update action', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $this->get(route('admin.orders.show', $order))->assertRedirect(route('login'));
});

test('an admin can update an order status from the order detail page and sees the new badge', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    Volt::actingAs($admin)->test('admin.orders.show', ['orderId' => $order->id])
        ->call('updateStatus', OrderStatus::Confirmed->value)
        ->assertSee('Confirmed');

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

test('an invalid status update from the order detail page does not change the order', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    Volt::actingAs($admin)->test('admin.orders.show', ['orderId' => $order->id])
        ->call('updateStatus', OrderStatus::Completed->value);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending);
});

test('the orders list offers a one-click advance action to the next lifecycle status', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-ADVANCE-001',
        'status' => OrderStatus::Pending,
    ]);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->call('advanceStatus', $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

test('the orders list advance action respects the lifecycle for a completed order', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Completed]);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->call('advanceStatus', $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::Completed);
});

test('customer cancellation behavior is unaffected by the admin status-update feature', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    Volt::actingAs($user)->test('customer.orders.show', ['orderId' => $order->id])
        ->call('cancel');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
    expect($order->cancellation_reason)->toBe('Cancelled by customer');
});
