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
            'Coffee' => [
                ['name' => 'Espresso', 'description' => 'A concentrated shot of our house blend.', 'price' => 2.75, 'prep' => 3, 'variants' => ['Size' => ['Single' => 0, 'Double' => 0.75]]],
                ['name' => 'Americano', 'description' => 'Espresso topped with hot water.', 'price' => 3.25, 'prep' => 4, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1]]],
                ['name' => 'Latte', 'description' => 'Espresso with steamed milk and a thin layer of foam.', 'price' => 4.25, 'prep' => 5, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1], 'Milk Type' => ['Whole' => 0, 'Oat' => 0.6, 'Almond' => 0.6, 'Skim' => 0]]],
                ['name' => 'Cappuccino', 'description' => 'Equal parts espresso, steamed milk, and foam.', 'price' => 4.00, 'prep' => 5, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1]]],
                ['name' => 'Mocha', 'description' => 'Espresso with chocolate and steamed milk.', 'price' => 4.75, 'prep' => 6, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1]]],
                ['name' => 'Flat White', 'description' => 'Espresso with velvety microfoam milk.', 'price' => 4.50, 'prep' => 5, 'variants' => null],
            ],
            'Tea' => [
                ['name' => 'English Breakfast Tea', 'description' => 'A robust, full-bodied black tea blend.', 'price' => 3.00, 'prep' => 4, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1]]],
                ['name' => 'Green Tea', 'description' => 'Light and grassy steeped green tea.', 'price' => 3.00, 'prep' => 4, 'variants' => null],
                ['name' => 'Chai Latte', 'description' => 'Spiced black tea with steamed milk.', 'price' => 4.00, 'prep' => 5, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1], 'Milk Type' => ['Whole' => 0, 'Oat' => 0.6, 'Almond' => 0.6, 'Skim' => 0]]],
            ],
            'Cold Drinks' => [
                ['name' => 'Iced Coffee', 'description' => 'Chilled house blend coffee over ice.', 'price' => 3.75, 'prep' => 3, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1]]],
                ['name' => 'Iced Latte', 'description' => 'Espresso with cold milk over ice.', 'price' => 4.50, 'prep' => 4, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1], 'Milk Type' => ['Whole' => 0, 'Oat' => 0.6, 'Almond' => 0.6, 'Skim' => 0]]],
                ['name' => 'Fresh Orange Juice', 'description' => 'Freshly squeezed orange juice.', 'price' => 3.50, 'prep' => 2, 'variants' => null],
                ['name' => 'Lemonade', 'description' => 'House-made lemonade over ice.', 'price' => 3.25, 'prep' => 2, 'variants' => null],
                ['name' => 'Strawberry Smoothie', 'description' => 'Blended strawberries, yogurt, and ice.', 'price' => 5.25, 'prep' => 5, 'variants' => null],
            ],
            'Breakfast' => [
                ['name' => 'Avocado Toast', 'description' => 'Smashed avocado on sourdough with chili flakes.', 'price' => 7.50, 'prep' => 8, 'variants' => null],
                ['name' => 'Eggs Benedict', 'description' => 'Poached eggs and hollandaise on an English muffin.', 'price' => 9.25, 'prep' => 12, 'variants' => null],
                ['name' => 'Granola Bowl', 'description' => 'House granola with yogurt and fresh berries.', 'price' => 6.50, 'prep' => 5, 'variants' => null],
                ['name' => 'Pancake Stack', 'description' => 'Fluffy pancakes with maple syrup.', 'price' => 7.75, 'prep' => 10, 'variants' => null],
            ],
            'Sandwiches' => [
                ['name' => 'Grilled Cheese', 'description' => 'Melted cheddar on toasted sourdough.', 'price' => 6.25, 'prep' => 7, 'variants' => null],
                ['name' => 'Club Sandwich', 'description' => 'Turkey, bacon, lettuce, and tomato on toast.', 'price' => 8.50, 'prep' => 10, 'variants' => null],
                ['name' => 'Chicken Panini', 'description' => 'Grilled chicken, pesto, and mozzarella.', 'price' => 8.75, 'prep' => 9, 'variants' => null],
                ['name' => 'Veggie Wrap', 'description' => 'Grilled vegetables and hummus in a soft wrap.', 'price' => 7.25, 'prep' => 6, 'variants' => null],
            ],
            'Pastries' => [
                ['name' => 'Butter Croissant', 'description' => 'Flaky, all-butter croissant.', 'price' => 3.25, 'prep' => 2, 'variants' => null],
                ['name' => 'Chocolate Muffin', 'description' => 'Rich chocolate chip muffin.', 'price' => 3.50, 'prep' => 2, 'variants' => null],
                ['name' => 'Cinnamon Roll', 'description' => 'Warm roll with cinnamon sugar and icing.', 'price' => 3.75, 'prep' => 2, 'variants' => null],
                ['name' => 'Blueberry Scone', 'description' => 'Crumbly scone studded with blueberries.', 'price' => 3.50, 'prep' => 2, 'variants' => null],
            ],
            'Desserts' => [
                ['name' => 'New York Cheesecake', 'description' => 'Classic creamy cheesecake slice.', 'price' => 5.50, 'prep' => 2, 'variants' => null],
                ['name' => 'Chocolate Brownie', 'description' => 'Fudgy chocolate brownie.', 'price' => 4.25, 'prep' => 2, 'variants' => null],
                ['name' => 'Tiramisu', 'description' => 'Espresso-soaked layers with mascarpone.', 'price' => 5.75, 'prep' => 2, 'variants' => null],
            ],
            'Specials' => [
                ['name' => 'Seasonal Pumpkin Latte', 'description' => 'Espresso with pumpkin spice and steamed milk.', 'price' => 5.25, 'prep' => 5, 'featured' => true, 'variants' => ['Size' => ['Small' => 0, 'Medium' => 0.5, 'Large' => 1]]],
                ['name' => "Chef's Breakfast Plate", 'description' => 'Eggs, bacon, toast, and roasted tomatoes.', 'price' => 10.50, 'prep' => 15, 'featured' => true, 'variants' => null],
            ],
        ];

        foreach ($catalog as $categoryName => $items) {
            $category = MenuCategory::where('slug', Str::slug($categoryName))->firstOrFail();

            foreach ($items as $sortOrder => $item) {
                $menuItem = MenuItem::factory()->create([
                    'menu_category_id' => $category->id,
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']),
                    'description' => $item['description'],
                    'base_price' => $item['price'],
                    'is_featured' => $item['featured'] ?? false,
                    'preparation_time_minutes' => $item['prep'],
                    'sort_order' => $sortOrder,
                ]);

                $this->createVariantGroups($menuItem, $item['variants'] ?? null);
            }
        }
    }

    /**
     * @param  array<string, array<string, float>>|null  $groups
     */
    private function createVariantGroups(MenuItem $menuItem, ?array $groups): void
    {
        if ($groups === null) {
            return;
        }

        $groupSortOrder = 0;

        foreach ($groups as $groupName => $variants) {
            $group = $menuItem->variantGroups()->create([
                'name' => $groupName,
                'selection_type' => VariantSelectionType::Single,
                'is_required' => true,
                'sort_order' => $groupSortOrder++,
            ]);

            $variantSortOrder = 0;

            foreach ($variants as $variantName => $priceModifier) {
                $group->variants()->create([
                    'name' => $variantName,
                    'price_modifier' => $priceModifier,
                    'is_available' => true,
                    'sort_order' => $variantSortOrder++,
                ]);
            }
        }
    }
}
