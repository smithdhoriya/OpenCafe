<?php

namespace Database\Factories;

use App\Enums\OfferType;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(OfferType::cases());

        return [
            'code' => Str::upper(fake()->unique()->bothify('OFFER-####')),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'type' => $type,
            'value' => $type === OfferType::Percentage
                ? fake()->numberBetween(5, 50)
                : fake()->randomFloat(2, 1, 20),
            'min_order_total' => fake()->optional()->randomFloat(2, 10, 50),
            'max_discount_amount' => $type === OfferType::Percentage
                ? fake()->optional()->randomFloat(2, 5, 30)
                : null,
            'starts_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'ends_at' => now()->addDays(fake()->numberBetween(10, 90)),
            'usage_limit' => fake()->optional()->numberBetween(50, 500),
            'usage_limit_per_user' => fake()->optional()->numberBetween(1, 3),
            'times_used' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the offer is a percentage discount.
     */
    public function percentage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OfferType::Percentage,
            'value' => fake()->numberBetween(5, 50),
            'max_discount_amount' => fake()->optional()->randomFloat(2, 5, 30),
        ]);
    }

    /**
     * Indicate that the offer is a fixed-amount discount.
     */
    public function fixedAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OfferType::FixedAmount,
            'value' => fake()->randomFloat(2, 1, 20),
            'max_discount_amount' => null,
        ]);
    }

    /**
     * Indicate that the offer has already ended.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(10),
        ]);
    }

    /**
     * Indicate that the offer is disabled.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
