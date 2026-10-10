<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * The statuses this order may move to next, given its fulfillment type and
     * payment status. This is the single source of truth for the order
     * lifecycle — admin status updates are only ever allowed to pass through
     * here, never by writing a status column directly.
     *
     * - Out for Delivery only ever applies to delivery orders; dine-in/takeaway
     *   orders go straight from Ready to Completed.
     * - Cancellation is only offered before the kitchen has marked the order
     *   Ready — once it's ready, dispatched, or served, a problem is handled
     *   as a Refund instead of a Cancellation.
     * - Refunded is only reachable from Completed or Cancelled, and only when
     *   money was actually taken (payment_status is Paid). This flips the
     *   order's lifecycle status for record-keeping only — it does not call
     *   any payment gateway or move money.
     */
    public function allowedTransitions(OrderType $type, PaymentStatus $paymentStatus): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready, self::Cancelled],
            self::Ready => [$type === OrderType::Delivery ? self::OutForDelivery : self::Completed],
            self::OutForDelivery => [self::Completed],
            self::Completed, self::Cancelled => $paymentStatus === PaymentStatus::Paid ? [self::Refunded] : [],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target, OrderType $type, PaymentStatus $paymentStatus): bool
    {
        return in_array($target, $this->allowedTransitions($type, $paymentStatus), true);
    }

    /**
     * The single "next step" in the normal happy-path lifecycle, excluding the
     * side-paths (Cancelled/Refunded). Null once the order is in a terminal or
     * side-path state. Used to offer a one-click "advance" action.
     */
    public function nextForwardStatus(OrderType $type): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Preparing,
            self::Preparing => self::Ready,
            self::Ready => $type === OrderType::Delivery ? self::OutForDelivery : self::Completed,
            self::OutForDelivery => self::Completed,
            default => null,
        };
    }
}
