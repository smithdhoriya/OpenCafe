<?php

namespace Database\Factories;

use App\Models\Combo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Combo>
 */
class ComboFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true)).' Combo';
        $price = fake()->randomFloat(2, 8, 25);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => $price,
            'compare_at_price' => $price + fake()->randomFloat(2, 1, 5),
            'image_path' => null,
            'is_available' => true,
            'starts_at' => null,
            'ends_at' => null,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Indicate that the combo is currently unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
