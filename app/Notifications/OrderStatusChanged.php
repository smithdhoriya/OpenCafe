<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $recipientName = $order->user?->name ?? $order->guest_name ?? 'there';
        $statusLabel = ucfirst(str_replace('_', ' ', $order->status->value));

        $message = (new MailMessage)
            ->subject("Order {$order->order_number} is now {$statusLabel}")
            ->greeting("Hi {$recipientName},")
            ->line("Your order {$order->order_number} is now: {$statusLabel}.");

        if ($order->status === OrderStatus::Cancelled && $order->cancellation_reason) {
            $message->line("Reason: {$order->cancellation_reason}");
        }

        if ($order->user_id !== null) {
            $message->action('View your order', route('orders.show', $order));
        }

        return $message;
    }
}
