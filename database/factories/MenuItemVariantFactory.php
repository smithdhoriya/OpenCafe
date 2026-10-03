<?php

namespace Database\Factories;

use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemVariant>
 */
class MenuItemVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_item_variant_group_id' => MenuItemVariantGroup::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large']),
            'price_modifier' => fake()->randomFloat(2, -1, 3),
            'is_available' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the variant is currently unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
