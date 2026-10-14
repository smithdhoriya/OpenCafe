<?php

namespace App\Notifications;

use App\Models\CafeSetting;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification implements ShouldQueue
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
        $order = $this->order->loadMissing(['items', 'delivery']);
        $symbol = CafeSetting::currencySymbol();
        $recipientName = $order->user?->name ?? $order->guest_name ?? 'there';

        $message = (new MailMessage)
            ->subject("Your OpenCafe order {$order->order_number} is confirmed")
            ->greeting("Hi {$recipientName},")
            ->line("Thanks for your order! Here's what we're getting ready for you:");

        foreach ($order->items as $item) {
            $label = $item->quantity.' × '.$item->item_name.($item->variant_name ? " ({$item->variant_name})" : '');
            $message->line($label.' — '.$symbol.number_format((float) $item->line_total, 2));
        }

        $message->line('Subtotal: '.$symbol.number_format((float) $order->subtotal, 2));

        if ((float) $order->discount_total > 0) {
            $message->line('Discount: -'.$symbol.number_format((float) $order->discount_total, 2));
        }

        if ((float) $order->delivery_charge > 0) {
            $message->line('Delivery charge: '.$symbol.number_format((float) $order->delivery_charge, 2));
        }

        $message->line('Total: '.$symbol.number_format((float) $order->total, 2));

        if ($order->delivery) {
            $message->line('Delivering to: '.$order->delivery->address_line_1.', '.$order->delivery->city);
        } else {
            $message->line('Fulfillment: '.ucfirst(str_replace('_', ' ', $order->type->value)));
        }

        $message->line('Current status: '.ucfirst(str_replace('_', ' ', $order->status->value)));

        if ($order->user_id !== null) {
            $message->action('View your order', route('orders.show', $order));
        }

        return $message;
    }
}
