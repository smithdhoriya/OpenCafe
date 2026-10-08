<?php

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

test('a customer can cancel their own pending order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    Volt::actingAs($user)->test('customer.orders.show', ['orderId' => $order->id])
        ->call('cancel');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('a pending order becomes cancelled', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    app(CancelOrder::class)->handle($user, $order->id);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

test('cancelling a pending order records the cancellation metadata', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    app(CancelOrder::class)->handle($user, $order->id);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelled_at)->not->toBeNull();
    expect($order->cancellation_reason)->toBe('Cancelled by customer');
});

test('a cancelled order is visible with cancelled status', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    app(CancelOrder::class)->handle($user, $order->id);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Cancelled');
});

test('orders that are not pending cannot be cancelled', function (OrderStatus $status) {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => $status]);

    expect(fn () => app(CancelOrder::class)->handle($user, $order->id))
        ->toThrow(ValidationException::class);

    $order->refresh();

    expect($order->status)->toBe($status);
    expect($order->cancelled_at)->toBeNull();
    expect($order->cancellation_reason)->toBeNull();
})->with([
    'confirmed' => OrderStatus::Confirmed,
    'preparing' => OrderStatus::Preparing,
    'ready' => OrderStatus::Ready,
    'out for delivery' => OrderStatus::OutForDelivery,
    'completed' => OrderStatus::Completed,
]);

test('another users order cannot be cancelled', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $order = Order::factory()->for($owner)->pending()->create();

    expect(fn () => app(CancelOrder::class)->handle($intruder, $order->id))
        ->toThrow(ValidationException::class);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->cancelled_at)->toBeNull();
    expect($order->cancellation_reason)->toBeNull();
});

test('a guest cannot reach the cancellation action', function () {
    $order = Order::factory()->pending()->create();

    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
});

test('an already cancelled order cannot be cancelled again', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => OrderStatus::Cancelled,
        'cancelled_at' => null,
        'cancellation_reason' => null,
    ]);

    expect(fn () => app(CancelOrder::class)->handle($user, $order->id))
        ->toThrow(ValidationException::class);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->cancelled_at)->toBeNull();
    expect($order->cancellation_reason)->toBeNull();
});

test('the cancellation action is not shown when cancellation is unavailable', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatus::Confirmed]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertDontSee('Cancel order');
});

test('the cancellation action is shown while the order is pending', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Cancel order');
});
