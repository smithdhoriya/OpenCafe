<?php

namespace Database\Seeders;

use App\Models\Combo;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ComboSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $combos = [
            [
                'name' => 'Breakfast Starter Combo',
                'description' => 'Avocado toast with a medium Americano.',
                'price' => 9.50,
                'compare_at_price' => 11.75,
                'items' => [
                    ['item' => 'Avocado Toast', 'quantity' => 1],
                    ['item' => 'Americano', 'quantity' => 1, 'variant' => 'Medium'],
                ],
            ],
            [
                'name' => 'Coffee & Pastry Combo',
                'description' => 'A medium latte with a butter croissant.',
                'price' => 6.75,
                'compare_at_price' => 7.75,
                'items' => [
                    ['item' => 'Latte', 'quantity' => 1, 'variant' => 'Medium'],
                    ['item' => 'Butter Croissant', 'quantity' => 1],
                ],
            ],
            [
                'name' => 'Lunch Combo',
                'description' => 'A club sandwich with an iced coffee and a brownie.',
                'price' => 14.50,
                'compare_at_price' => 17.00,
                'items' => [
                    ['item' => 'Club Sandwich', 'quantity' => 1],
                    ['item' => 'Iced Coffee', 'quantity' => 1, 'variant' => 'Medium'],
                    ['item' => 'Chocolate Brownie', 'quantity' => 1],
                ],
            ],
            [
                'name' => 'Tea Time Combo',
                'description' => 'A chai latte with a blueberry scone.',
                'price' => 6.50,
                'compare_at_price' => 7.50,
                'items' => [
                    ['item' => 'Chai Latte', 'quantity' => 1, 'variant' => 'Medium'],
                    ['item' => 'Blueberry Scone', 'quantity' => 1],
                ],
            ],
        ];

        foreach ($combos as $sortOrder => $comboData) {
            $combo = Combo::factory()->create([
                'name' => $comboData['name'],
                'slug' => Str::slug($comboData['name']),
                'description' => $comboData['description'],
                'price' => $comboData['price'],
                'compare_at_price' => $comboData['compare_at_price'],
                'sort_order' => $sortOrder,
            ]);

            foreach ($comboData['items'] as $comboItem) {
                $menuItem = MenuItem::where('slug', Str::slug($comboItem['item']))->firstOrFail();

                $variant = isset($comboItem['variant'])
                    ? MenuItemVariant::whereHas(
                        'group',
                        fn ($query) => $query->where('menu_item_id', $menuItem->id)
                    )->where('name', $comboItem['variant'])->first()
                    : null;

                $combo->comboItems()->create([
                    'menu_item_id' => $menuItem->id,
                    'menu_item_variant_id' => $variant?->id,
                    'quantity' => $comboItem['quantity'],
                ]);
            }
        }
    }
}
