<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(private readonly NotifyOrderCustomer $notifyOrderCustomer) {}

    /**
     * Cancel one of the authenticated user's orders, but only while it is still
     * Pending. Ownership scoping and the status check happen in the same
     * conditional update, so a status change racing with this call (or a
     * repeat/double-submit) can never slip through.
     */
    public function handle(User $user, int $orderId): Order
    {
        $order = $user->orders()->whereKey($orderId)->first();

        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Order not found.']);
        }

        $cancelledAt = now();

        $cancelled = $user->orders()
            ->whereKey($orderId)
            ->where('status', OrderStatus::Pending)
            ->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => 'Cancelled by customer',
            ]);

        if (! $cancelled) {
            throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
        }

        $order->status = OrderStatus::Cancelled;
        $order->cancelled_at = $cancelledAt;
        $order->cancellation_reason = 'Cancelled by customer';

        $this->notifyOrderCustomer->handle($order, new OrderStatusChanged($order));

        return $order;
    }
}
