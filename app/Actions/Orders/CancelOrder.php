<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
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

        $cancelled = $user->orders()
            ->whereKey($orderId)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Cancelled]);

        if (! $cancelled) {
            throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
        }

        $order->status = OrderStatus::Cancelled;

        return $order;
    }
}
