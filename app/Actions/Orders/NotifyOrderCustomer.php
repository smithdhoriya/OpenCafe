<?php

namespace App\Actions\Orders;

use App\Models\Order;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotifyOrderCustomer
{
    /**
     * Deliver an order-related notification to whichever recipient the order
     * actually has — a registered user, or (for guest orders) the email
     * captured at checkout. Skipped, with a log entry, when neither is
     * available, so a missing address can never break the order flow.
     */
    public function handle(Order $order, Notification $notification): void
    {
        if ($order->user !== null) {
            $order->user->notify($notification);

            return;
        }

        if ($order->guest_email !== null) {
            NotificationFacade::route('mail', $order->guest_email)->notify($notification);

            return;
        }

        Log::warning('Skipped order notification: no recipient email on file.', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'notification' => $notification::class,
        ]);
    }
}
