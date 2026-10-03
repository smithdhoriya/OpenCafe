<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_variant_id' => null,
            'quantity' => fake()->numberBetween(1, 3),
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }
}
