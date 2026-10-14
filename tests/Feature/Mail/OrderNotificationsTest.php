<?php

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\CreateOrderFromCart;
use App\Actions\Orders\NotifyOrderCustomer;
use App\Actions\Orders\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderStatusChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

test('placing an order sends an order confirmation to the customer', function () {
    seedDeliveryConfig();
    Notification::fake();
    $user = User::factory()->create();
    addCartItem($user, 150, 2);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    Notification::assertSentTo($user, OrderConfirmation::class, function ($notification) use ($order) {
        return $notification->order->is($order);
    });
});

test('a failed order creation never sends a confirmation email', function () {
    seedDeliveryConfig();
    Notification::fake();
    $user = User::factory()->create();
    // No cart items — CreateOrderFromCart throws before anything commits.

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null))
        ->toThrow(ValidationException::class);

    Notification::assertNothingSent();
});

test('customer-visible status transitions email the customer', function (OrderStatus $status, array $setup = []) {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(array_merge(['status' => OrderStatus::Pending], $setup));

    app(UpdateOrderStatus::class)->handle($admin, $order->id, $status);

    Notification::assertSentTo($user, OrderStatusChanged::class);
})->with([
    'confirmed' => [OrderStatus::Confirmed],
    'cancelled' => [OrderStatus::Cancelled],
    'refunded' => [OrderStatus::Refunded, ['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid]],
]);

test('advancing to preparing does not email the customer', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatus::Confirmed]);

    app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Preparing);

    Notification::assertNothingSent();
});

test('a rejected status transition never sends an email', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatus::Refunded]);

    expect(fn () => app(UpdateOrderStatus::class)->handle($admin, $order->id, OrderStatus::Completed))
        ->toThrow(ValidationException::class);

    Notification::assertNothingSent();
});

test('a double submission of the same transition only sends one status email', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create(['status' => OrderStatus::Pending]);
    $action = app(UpdateOrderStatus::class);

    $action->handle($admin, $order->id, OrderStatus::Confirmed);

    expect(fn () => $action->handle($admin, $order->id, OrderStatus::Confirmed))
        ->toThrow(ValidationException::class);

    Notification::assertSentTimes(OrderStatusChanged::class, 1);
});

test('a customer cancelling their own order sends a status email', function () {
    Notification::fake();
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->pending()->create();

    app(CancelOrder::class)->handle($user, $order->id);

    Notification::assertSentTo($user, OrderStatusChanged::class);
});

test('an order with no recipient email is skipped gracefully', function () {
    Notification::fake();
    $order = Order::factory()->create(['user_id' => null, 'guest_name' => null, 'guest_email' => null]);

    app(NotifyOrderCustomer::class)->handle($order, new OrderConfirmation($order));

    Notification::assertNothingSent();
});

test('a guest order with an email on file is still notified', function () {
    Notification::fake();
    $order = Order::factory()->guest()->create();

    app(NotifyOrderCustomer::class)->handle($order, new OrderConfirmation($order));

    Notification::assertSentOnDemand(OrderConfirmation::class);
});

test('the order confirmation mail content includes items, totals, and a view-order link', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150, 2);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    $mail = (new OrderConfirmation($order))->toMail($user);

    expect($mail->subject)->toContain($order->order_number);
    expect($mail->actionText)->toBe('View your order');
    expect($mail->actionUrl)->toContain((string) $order->id);
    expect(implode(' ', $mail->introLines))->toContain('300.00');
});

test('the order confirmation mail omits the view-order link for guest orders', function () {
    $order = Order::factory()->guest()->create();

    $mail = (new OrderConfirmation($order))->toMail($order);

    expect($mail->actionText)->toBeNull();
});

test('the status-changed mail reports the cancellation reason when present', function () {
    $user = User::factory()->create();
    $order = Order::factory()->for($user)->create([
        'status' => OrderStatus::Cancelled,
        'cancellation_reason' => 'Out of stock',
    ]);

    $mail = (new OrderStatusChanged($order))->toMail($user);

    expect(implode(' ', $mail->introLines))->toContain('Out of stock');
});
