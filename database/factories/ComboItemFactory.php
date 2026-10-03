<?php

namespace Database\Factories;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComboItem>
 */
class ComboItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'combo_id' => Combo::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_variant_id' => null,
            'quantity' => 1,
        ];
    }
}
