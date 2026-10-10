<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;

test('the happy path lifecycle advances one step at a time for a delivery order', function () {
    expect(OrderStatus::Pending->nextForwardStatus(OrderType::Delivery))->toBe(OrderStatus::Confirmed);
    expect(OrderStatus::Confirmed->nextForwardStatus(OrderType::Delivery))->toBe(OrderStatus::Preparing);
    expect(OrderStatus::Preparing->nextForwardStatus(OrderType::Delivery))->toBe(OrderStatus::Ready);
    expect(OrderStatus::Ready->nextForwardStatus(OrderType::Delivery))->toBe(OrderStatus::OutForDelivery);
    expect(OrderStatus::OutForDelivery->nextForwardStatus(OrderType::Delivery))->toBe(OrderStatus::Completed);
    expect(OrderStatus::Completed->nextForwardStatus(OrderType::Delivery))->toBeNull();
});

test('ready skips straight to completed for non-delivery orders', function (OrderType $type) {
    expect(OrderStatus::Ready->nextForwardStatus($type))->toBe(OrderStatus::Completed);
})->with([
    'takeaway' => OrderType::Takeaway,
    'dine-in' => OrderType::DineIn,
]);

test('out for delivery is never an allowed transition for non-delivery orders', function (OrderType $type) {
    $transitions = OrderStatus::Ready->allowedTransitions($type, PaymentStatus::Unpaid);

    expect($transitions)->not->toContain(OrderStatus::OutForDelivery);
})->with([
    'takeaway' => OrderType::Takeaway,
    'dine-in' => OrderType::DineIn,
]);

test('cancellation is only ever offered before ready', function () {
    expect(OrderStatus::Pending->allowedTransitions(OrderType::Takeaway, PaymentStatus::Unpaid))->toContain(OrderStatus::Cancelled);
    expect(OrderStatus::Confirmed->allowedTransitions(OrderType::Takeaway, PaymentStatus::Unpaid))->toContain(OrderStatus::Cancelled);
    expect(OrderStatus::Preparing->allowedTransitions(OrderType::Takeaway, PaymentStatus::Unpaid))->toContain(OrderStatus::Cancelled);

    expect(OrderStatus::Ready->allowedTransitions(OrderType::Takeaway, PaymentStatus::Unpaid))->not->toContain(OrderStatus::Cancelled);
    expect(OrderStatus::OutForDelivery->allowedTransitions(OrderType::Delivery, PaymentStatus::Unpaid))->not->toContain(OrderStatus::Cancelled);
    expect(OrderStatus::Completed->allowedTransitions(OrderType::Takeaway, PaymentStatus::Unpaid))->not->toContain(OrderStatus::Cancelled);
});

test('refunded is only reachable from completed or cancelled, and only when paid', function (OrderStatus $from) {
    expect($from->allowedTransitions(OrderType::Takeaway, PaymentStatus::Paid))->toBe([OrderStatus::Refunded]);
    expect($from->allowedTransitions(OrderType::Takeaway, PaymentStatus::Unpaid))->toBe([]);
})->with([
    'completed' => OrderStatus::Completed,
    'cancelled' => OrderStatus::Cancelled,
]);

test('refunded has no outgoing transitions', function () {
    expect(OrderStatus::Refunded->allowedTransitions(OrderType::Delivery, PaymentStatus::Paid))->toBe([]);
});

test('canTransitionTo matches the allowed-transitions list', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed, OrderType::Takeaway, PaymentStatus::Unpaid))->toBeTrue();
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Ready, OrderType::Takeaway, PaymentStatus::Unpaid))->toBeFalse();
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Refunded, OrderType::Takeaway, PaymentStatus::Paid))->toBeFalse();
});
