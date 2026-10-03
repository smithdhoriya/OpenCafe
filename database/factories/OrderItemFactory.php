<?php

namespace Database\Factories;

use App\Models\Combo;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'menu_item_variant_id' => null,
            'combo_id' => null,
            'item_name' => null,
            'variant_name' => null,
            'combo_components_snapshot' => null,
            'unit_price' => 0,
            'quantity' => fake()->numberBetween(1, 3),
            'line_total' => 0,
            'special_instructions' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Fill in the frozen name/price snapshot from the related menu item or combo,
     * unless the caller already supplied an explicit snapshot (e.g. a seeder building
     * a coherent order where the subtotal must match the sum of its lines).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $orderItem) {
            if ($orderItem->item_name !== null) {
                return;
            }

            if ($orderItem->combo_id !== null) {
                $combo = Combo::find($orderItem->combo_id) ?? Combo::factory()->create();

                $orderItem->combo_id = $combo->id;
                $orderItem->item_name = $combo->name;
                $orderItem->unit_price = $combo->price;
                $orderItem->combo_components_snapshot = [];
            } else {
                $menuItem = MenuItem::find($orderItem->menu_item_id) ?? MenuItem::factory()->create();

                $orderItem->menu_item_id = $menuItem->id;
                $orderItem->item_name = $menuItem->name;
                $orderItem->unit_price = $menuItem->base_price;
            }

            $orderItem->line_total = $orderItem->unit_price * $orderItem->quantity;
        });
    }

    /**
     * Indicate that this line item represents a combo rather than a single menu item.
     */
    public function forCombo(): static
    {
        return $this->state(fn (array $attributes) => [
            'menu_item_id' => null,
            'menu_item_variant_id' => null,
            'combo_id' => Combo::factory(),
            'item_name' => null,
        ]);
    }
}
