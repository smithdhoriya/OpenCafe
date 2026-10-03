<?php

namespace Database\Factories;

use App\Models\DeliveryRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRate>
 */
class DeliveryRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = fake()->randomFloat(2, 0, 10);

        return [
            'min_distance_km' => $min,
            'max_distance_km' => $min + 5,
            'charge' => fake()->randomFloat(2, 1, 10),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that this is the open-ended, top distance tier.
     */
    public function openEnded(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_distance_km' => null,
        ]);
    }
}
