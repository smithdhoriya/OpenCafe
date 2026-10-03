<?php

namespace Database\Factories;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_token' => null,
            'status' => CartStatus::Active,
            'expires_at' => now()->addDay(),
        ];
    }

    /**
     * Indicate that this is an anonymous, guest cart tracked by a session token.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_token' => (string) Str::uuid(),
        ]);
    }

    /**
     * Indicate that the cart has already been converted into an order.
     */
    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CartStatus::Converted,
        ]);
    }

    /**
     * Indicate that the cart was abandoned.
     */
    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CartStatus::Abandoned,
            'expires_at' => now()->subDay(),
        ]);
    }
}
