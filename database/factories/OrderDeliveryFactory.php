<?php

namespace Database\Factories;

use App\Models\DeliveryRate;
use App\Models\Order;
use App\Models\OrderDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDelivery>
 */
class OrderDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->delivery(),
            'customer_address_id' => null,
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('+1##########'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'United States',
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'distance_km' => fake()->randomFloat(2, 1, 10),
            'delivery_rate_id' => DeliveryRate::factory(),
            'delivery_charge' => fake()->randomFloat(2, 2, 8),
            'delivered_at' => null,
        ];
    }

    /**
     * Indicate that the delivery has already been completed.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivered_at' => now()->subMinutes(fake()->numberBetween(5, 120)),
        ]);
    }
}
