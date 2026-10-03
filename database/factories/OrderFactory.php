<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 5, 50);

        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'user_id' => User::factory(),
            'guest_name' => null,
            'guest_email' => null,
            'guest_phone' => null,
            'type' => OrderType::Takeaway,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => null,
            'currency' => 'USD',
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'delivery_charge' => 0,
            'tax_total' => 0,
            'total' => $subtotal,
            'offer_id' => null,
            'offer_code' => null,
            'notes' => null,
            'cancellation_reason' => null,
            'placed_at' => now(),
            'confirmed_at' => null,
            'ready_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Indicate that this is an unauthenticated guest order.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->numerify('+1##########'),
        ]);
    }

    /**
     * Indicate that the order has been placed but not yet confirmed.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Unpaid,
            'placed_at' => now(),
            'confirmed_at' => null,
            'ready_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    /**
     * Indicate that this is a delivery order.
     */
    public function delivery(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrderType::Delivery,
        ]);
    }

    /**
     * Indicate that the order has been fully completed and paid.
     */
    public function completed(): static
    {
        $placedAt = now()->subHours(fake()->numberBetween(2, 72));

        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => fake()->randomElement(['card', 'cash', 'wallet']),
            'placed_at' => $placedAt,
            'confirmed_at' => (clone $placedAt)->addMinutes(2),
            'ready_at' => (clone $placedAt)->addMinutes(15),
            'completed_at' => (clone $placedAt)->addMinutes(25),
        ]);
    }

    /**
     * Indicate that the order was cancelled.
     */
    public function cancelled(): static
    {
        $placedAt = now()->subHours(fake()->numberBetween(2, 72));

        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
            'placed_at' => $placedAt,
            'confirmed_at' => (clone $placedAt)->addMinutes(2),
            'cancelled_at' => (clone $placedAt)->addMinutes(10),
            'cancellation_reason' => fake()->randomElement([
                'Customer requested cancellation',
                'Out of stock',
                'Unable to deliver to address',
            ]),
        ]);
    }
}
