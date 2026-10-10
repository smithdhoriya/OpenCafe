<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateOrderStatus
{
    /**
     * Move an order to a new status, as an admin action.
     *
     * The target status is only ever accepted if it's a valid next step from
     * the order's *current, database-held* status — never trusted at face
     * value from the request. The status check and the update happen in the
     * same conditional query, so a double submission or a second admin
     * racing this call can never both succeed.
     */
    public function handle(User $admin, int $orderId, OrderStatus $to): Order
    {
        $order = Order::query()->whereKey($orderId)->first();

        if (! $order) {
            throw ValidationException::withMessages(['status' => 'Order not found.']);
        }

        if (! $order->status->canTransitionTo($to, $order->type, $order->payment_status)) {
            throw ValidationException::withMessages([
                'status' => "This order can't be moved from \"{$order->status->value}\" to \"{$to->value}\".",
            ]);
        }

        $now = now();

        $attributes = ['status' => $to];

        if ($to === OrderStatus::Confirmed) {
            $attributes['confirmed_at'] = $now;
        } elseif ($to === OrderStatus::Ready) {
            $attributes['ready_at'] = $now;
        } elseif ($to === OrderStatus::Completed) {
            $attributes['completed_at'] = $now;
        } elseif ($to === OrderStatus::Cancelled) {
            $attributes['cancelled_at'] = $now;
            $attributes['cancellation_reason'] = "Cancelled by admin ({$admin->name})";
        }

        $updated = Order::query()
            ->whereKey($orderId)
            ->where('status', $order->status)
            ->update($attributes);

        if (! $updated) {
            throw ValidationException::withMessages([
                'status' => 'This order was already updated elsewhere. Please refresh and try again.',
            ]);
        }

        $order->forceFill($attributes);

        return $order;
    }
}
