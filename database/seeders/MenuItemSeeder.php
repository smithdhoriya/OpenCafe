<?php

namespace Database\Seeders;

use App\Enums\VariantSelectionType;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = [
            'Burgers' => [
                ['name' => 'Cheese Burger', 'price' => 60, 'featured' => true],
                ['name' => 'Aloo Tikki Burger', 'price' => 40],
                ['name' => 'Mayonnaise Burger', 'price' => 50],
                ['name' => 'Schezwan Burger', 'price' => 50],
                ['name' => 'Garlic Cheese Burger', 'price' => 60],
            ],
            'Pizzas' => [
                ['name' => 'Tandoori Pizza', 'price' => 150],
                ['name' => 'American Garden Pizza', 'price' => 140],
                ['name' => 'Margherita Pizza', 'price' => 130, 'featured' => true],
                ['name' => '7 Cheese Pizza', 'price' => 130],
                ['name' => 'Italian Pizza', 'price' => 130],
                ['name' => 'Jain Italian', 'price' => 120],
                ['name' => 'Mexican Pizza', 'price' => 130],
            ],
            'Frankie' => [
                ['name' => 'Jain Frankie', 'price' => 50],
                ['name' => 'Aloo Frankie', 'price' => 50],
                ['name' => 'Noodles Frankie', 'price' => 50],
                ['name' => 'Paneer Frankie', 'price' => 70],
            ],
            'Coffee / Beverages' => [
                ['name' => 'Tea', 'price' => 10],
                ['name' => 'Coffee Small', 'price' => 20],
                ['name' => 'Hot Coffee Half', 'price' => 20],
                ['name' => 'Hot Coffee Full', 'price' => 30],
                ['name' => 'Cold Coffee', 'price' => 30, 'featured' => true],
                ['name' => 'Chaas', 'price' => 10],
            ],
            'Sandwich' => [
                ['name' => 'Plain Sandwich', 'price' => 30],
                ['name' => 'Veg Cheese Sandwich', 'price' => 50, 'featured' => true],
                ['name' => 'Grill Sandwich', 'price' => 70],
                ['name' => 'Cheese Grill', 'price' => 100],
                ['name' => 'Tandoori Sandwich', 'price' => 100],
                ['name' => 'Paneer Cheese Grill', 'price' => 120],
                ['name' => 'Masala Toast Sandwich', 'price' => 50],
                ['name' => 'Veg Toast Sandwich', 'price' => 50],
                ['name' => 'Masala Noodle Toast Sandwich', 'price' => 60],
            ],
            'Fries' => [
                ['name' => 'French Fries', 'price' => 60],
                ['name' => 'Mayo Fries', 'price' => 70],
                ['name' => 'Peri Peri Fries', 'price' => 70],
                ['name' => 'Cheesy Fries', 'price' => 80],
                ['name' => 'Special Fries', 'price' => 80],
            ],
            'Vada Pav' => [
                ['name' => 'Vada Pav', 'price' => 10],
                ['name' => 'Special Vada Pav', 'price' => 15],
                ['name' => 'Mayo Vada Pav', 'price' => 15],
                ['name' => 'Schezwan Vada Pav', 'price' => 15],
                ['name' => 'Garlic Vada Pav', 'price' => 20],
                ['name' => 'Cheese Garlic Vada Pav', 'price' => 30],
                ['name' => 'Cheese Vada Pav', 'price' => 25],
                ['name' => 'Grill Vada Pav', 'price' => 15],
                ['name' => 'Tawa Butter Vada Pav', 'price' => 20],
            ],
        ];

        // The only add-on on the physical menu card: optional cheese, priced per category.
        $cheeseAddonPriceByCategory = [
            'Pizzas' => 30,
            'Frankie' => 20,
            'Sandwich' => 20,
        ];

        foreach ($catalog as $categoryName => $items) {
            $category = MenuCategory::where('slug', Str::slug($categoryName))->firstOrFail();
            $cheeseAddonPrice = $cheeseAddonPriceByCategory[$categoryName] ?? null;

            foreach ($items as $sortOrder => $item) {
                $menuItem = MenuItem::factory()->create([
                    'menu_category_id' => $category->id,
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']),
                    'description' => null,
                    'base_price' => $item['price'],
                    'is_featured' => $item['featured'] ?? false,
                    'preparation_time_minutes' => null,
                    'sort_order' => $sortOrder,
                ]);

                if ($cheeseAddonPrice !== null) {
                    $this->addCheeseAddon($menuItem, $cheeseAddonPrice);
                }
            }
        }
    }

    /**
     * Attach an optional, non-required "Add-ons" group with a single Cheese variant.
     * Selection type must be Multiple so the default selection is empty (opt-in),
     * rather than Single defaulting to the first (and only) variant being pre-selected.
     */
    private function addCheeseAddon(MenuItem $menuItem, float $price): void
    {
        $group = $menuItem->variantGroups()->create([
            'name' => 'Add-ons',
            'selection_type' => VariantSelectionType::Multiple,
            'is_required' => false,
            'sort_order' => 0,
        ]);

        $group->variants()->create([
            'name' => 'Cheese',
            'price_modifier' => $price,
            'is_available' => true,
            'sort_order' => 0,
        ]);
    }
}
