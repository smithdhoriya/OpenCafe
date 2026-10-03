<?php

namespace Database\Factories;

use App\Enums\VariantSelectionType;
use App\Models\MenuItem;
use App\Models\MenuItemVariantGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemVariantGroup>
 */
class MenuItemVariantGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'name' => fake()->randomElement(['Size', 'Milk Type', 'Sweetness Level', 'Add-ons']),
            'selection_type' => VariantSelectionType::Single,
            'is_required' => false,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the group allows multiple selections.
     */
    public function multiple(): static
    {
        return $this->state(fn (array $attributes) => [
            'selection_type' => VariantSelectionType::Multiple,
        ]);
    }

    /**
     * Indicate that selecting a variant from this group is mandatory.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }
}
