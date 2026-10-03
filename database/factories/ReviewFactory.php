<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\MenuItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
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
            'order_id' => null,
            'menu_item_id' => MenuItem::factory(),
            'guest_name' => null,
            'guest_email' => null,
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->optional()->sentence(4),
            'comment' => fake()->optional()->paragraph(),
            'status' => ReviewStatus::Approved,
            'is_visible' => true,
            'moderated_by' => null,
            'moderated_at' => null,
            'moderation_notes' => null,
        ];
    }

    /**
     * Indicate that this is an unauthenticated guest review.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
        ]);
    }

    /**
     * Indicate that the review is awaiting moderation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Pending,
            'is_visible' => false,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);
    }

    /**
     * Indicate that the review was rejected by a moderator.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReviewStatus::Rejected,
            'is_visible' => false,
            'moderated_by' => User::factory()->staff(),
            'moderated_at' => now(),
            'moderation_notes' => fake()->sentence(),
        ]);
    }
}
