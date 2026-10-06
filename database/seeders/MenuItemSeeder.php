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
                [
                    'name' => 'Cheese Burger',
                    'price' => 60,
                    'featured' => true,
                    'description' => 'A warm sesame bun packed with a grilled veg patty and a melted layer of cheese, finished with crisp lettuce and tangy sauces.',
                    'ingredients' => ['Veg Patty', 'Melted Cheese', 'Lettuce', 'Tomato', 'Burger Sauce', 'Sesame Bun'],
                ],
                [
                    'name' => 'Aloo Tikki Burger',
                    'price' => 40,
                    'description' => 'A classic Indian street-food favourite — a crisp, spiced potato tikki layered with fresh veggies and chutneys in a soft bun.',
                    'ingredients' => ['Spiced Potato Tikki', 'Onion', 'Tomato', 'Green Chutney', 'Tangy Ketchup', 'Soft Bun'],
                ],
                [
                    'name' => 'Mayonnaise Burger',
                    'price' => 50,
                    'description' => 'A soft bun loaded with a crisp veg patty and a generous swirl of creamy mayonnaise for extra indulgence.',
                    'ingredients' => ['Veg Patty', 'Creamy Mayonnaise', 'Lettuce', 'Onion', 'Soft Bun'],
                ],
                [
                    'name' => 'Schezwan Burger',
                    'price' => 50,
                    'description' => 'An Indo-Chinese twist on the classic — a crispy patty tossed in fiery schezwan sauce for a bold, spicy kick.',
                    'ingredients' => ['Veg Patty', 'Schezwan Sauce', 'Cabbage', 'Onion', 'Soft Bun'],
                ],
                [
                    'name' => 'Garlic Cheese Burger',
                    'price' => 60,
                    'description' => 'A flavour-packed burger with a garlicky patty and melted cheese for a rich, savoury bite.',
                    'ingredients' => ['Veg Patty', 'Garlic Seasoning', 'Melted Cheese', 'Lettuce', 'Soft Bun'],
                ],
            ],
            'Pizzas' => [
                [
                    'name' => 'Tandoori Pizza',
                    'price' => 150,
                    'description' => 'A smoky, spiced pizza topped with tandoori-marinated vegetables, capsicum and onion over a melted layer of cheese.',
                    'ingredients' => ['Tandoori Sauce', 'Mixed Vegetables', 'Capsicum', 'Onion', 'Melted Cheese', 'Pizza Base'],
                ],
                [
                    'name' => 'American Garden Pizza',
                    'price' => 140,
                    'description' => 'A generously loaded garden-style pizza piled high with crisp vegetables, sweet corn and melted cheese.',
                    'ingredients' => ['Capsicum', 'Onion', 'Tomato', 'Sweet Corn', 'Melted Cheese', 'Pizza Base'],
                ],
                [
                    'name' => 'Margherita Pizza',
                    'price' => 130,
                    'featured' => true,
                    'description' => 'The timeless classic — a simple, fresh pizza topped with tangy tomato sauce, melted mozzarella and a touch of basil.',
                    'ingredients' => ['Tomato Sauce', 'Mozzarella Cheese', 'Basil', 'Pizza Base'],
                ],
                [
                    'name' => '7 Cheese Pizza',
                    'price' => 130,
                    'description' => "A rich, indulgent pizza topped with a blend of seven cheese varieties for the ultimate cheese lover's pizza. Exact cheese blend to be confirmed with final OpenCafe recipes.",
                    'ingredients' => ['Tomato Sauce', 'Seven Cheese Blend', 'Herbs', 'Pizza Base'],
                ],
                [
                    'name' => 'Italian Pizza',
                    'price' => 130,
                    'description' => 'A classic Italian-style pizza finished with herby tomato sauce, melted cheese and aromatic oregano.',
                    'ingredients' => ['Tomato Sauce', 'Melted Cheese', 'Capsicum', 'Oregano & Herbs', 'Pizza Base'],
                ],
                [
                    'name' => 'Jain Italian',
                    'price' => 120,
                    'description' => 'Our Italian-style pizza made Jain-friendly — no onion or garlic — topped with tomato sauce, melted cheese and herbs.',
                    'ingredients' => ['Tomato Sauce', 'Melted Cheese', 'Capsicum', 'Oregano & Herbs', 'Pizza Base', 'No Onion, No Garlic'],
                ],
                [
                    'name' => 'Mexican Pizza',
                    'price' => 130,
                    'description' => 'A zesty, Mexican-inspired pizza loaded with jalapeños, capsicum, sweet corn and a spiced tomato base.',
                    'ingredients' => ['Spiced Tomato Sauce', 'Jalapeños', 'Capsicum', 'Sweet Corn', 'Melted Cheese', 'Pizza Base'],
                ],
            ],
            'Frankie' => [
                [
                    'name' => 'Jain Frankie',
                    'price' => 50,
                    'description' => 'A Jain-friendly frankie roll — no onion or garlic — filled with spiced vegetables and rolled in a soft paratha.',
                    'ingredients' => ['Spiced Vegetables', 'Soft Paratha Roll', 'Tangy Chutney', 'No Onion, No Garlic'],
                ],
                [
                    'name' => 'Aloo Frankie',
                    'price' => 50,
                    'description' => 'A soft rolled paratha stuffed with spiced mashed potato filling and tangy chutneys.',
                    'ingredients' => ['Spiced Potato Filling', 'Onion', 'Green Chutney', 'Soft Paratha Roll'],
                ],
                [
                    'name' => 'Noodles Frankie',
                    'price' => 50,
                    'description' => 'A fun fusion roll stuffed with Indo-Chinese stir-fried noodles and crunchy vegetables.',
                    'ingredients' => ['Stir-Fried Noodles', 'Mixed Vegetables', 'Schezwan Sauce', 'Soft Paratha Roll'],
                ],
                [
                    'name' => 'Paneer Frankie',
                    'price' => 70,
                    'description' => 'A hearty roll filled with spiced paneer cubes, crunchy vegetables and tangy sauces, wrapped in a soft paratha.',
                    'ingredients' => ['Spiced Paneer', 'Onion & Capsicum', 'Mint Chutney', 'Soft Paratha Roll'],
                ],
            ],
            'Coffee / Beverages' => [
                [
                    'name' => 'Tea',
                    'price' => 10,
                    'description' => 'A comforting cup of classic Indian masala-style tea, brewed hot and strong.',
                    'ingredients' => ['Tea Leaves', 'Milk', 'Sugar', 'Warm Spices'],
                ],
                [
                    'name' => 'Coffee Small',
                    'price' => 20,
                    'description' => 'A quick, satisfying small cup of hot filter coffee.',
                    'ingredients' => ['Coffee Decoction', 'Milk', 'Sugar'],
                ],
                [
                    'name' => 'Hot Coffee Half',
                    'price' => 20,
                    'description' => 'A half-size serving of our hot, frothy filter coffee — perfect for a quick break.',
                    'ingredients' => ['Coffee Decoction', 'Milk', 'Sugar'],
                ],
                [
                    'name' => 'Hot Coffee Full',
                    'price' => 30,
                    'description' => 'A full-size serving of hot, frothy filter coffee to keep you going.',
                    'ingredients' => ['Coffee Decoction', 'Milk', 'Sugar'],
                ],
                [
                    'name' => 'Cold Coffee',
                    'price' => 30,
                    'featured' => true,
                    'description' => 'A chilled, creamy coffee-based beverage, blended smooth and served ice-cold.',
                    'ingredients' => ['Coffee Decoction', 'Chilled Milk', 'Sugar', 'Ice'],
                ],
                [
                    'name' => 'Chaas',
                    'price' => 10,
                    'description' => 'A refreshing, chilled spiced buttermilk, lightly tempered with cumin for a cooling finish.',
                    'ingredients' => ['Buttermilk', 'Roasted Cumin', 'Salt', 'Mint'],
                ],
            ],
            'Sandwich' => [
                [
                    'name' => 'Plain Sandwich',
                    'price' => 30,
                    'description' => 'A simple, comforting sandwich with fresh vegetables and a light layer of butter between soft bread slices.',
                    'ingredients' => ['Cucumber', 'Tomato', 'Onion', 'Butter', 'Bread'],
                ],
                [
                    'name' => 'Veg Cheese Sandwich',
                    'price' => 50,
                    'featured' => true,
                    'description' => 'A soft sandwich layered with fresh vegetables and a generous helping of melted cheese.',
                    'ingredients' => ['Cucumber', 'Tomato', 'Onion', 'Melted Cheese', 'Bread'],
                ],
                [
                    'name' => 'Grill Sandwich',
                    'price' => 70,
                    'description' => 'Fresh vegetables pressed and grilled between bread slices until golden and crisp.',
                    'ingredients' => ['Cucumber', 'Tomato', 'Onion', 'Butter', 'Grilled Bread'],
                ],
                [
                    'name' => 'Cheese Grill',
                    'price' => 100,
                    'description' => 'A golden, grilled sandwich oozing with melted cheese in every bite.',
                    'ingredients' => ['Melted Cheese', 'Tomato', 'Onion', 'Grilled Bread'],
                ],
                [
                    'name' => 'Tandoori Sandwich',
                    'price' => 100,
                    'description' => 'A grilled sandwich filled with tandoori-spiced vegetables for a smoky, bold flavour.',
                    'ingredients' => ['Tandoori-Spiced Vegetables', 'Capsicum', 'Onion', 'Grilled Bread'],
                ],
                [
                    'name' => 'Paneer Cheese Grill',
                    'price' => 120,
                    'description' => 'A rich grilled sandwich packed with spiced paneer and melted cheese.',
                    'ingredients' => ['Spiced Paneer', 'Melted Cheese', 'Capsicum', 'Grilled Bread'],
                ],
                [
                    'name' => 'Masala Toast Sandwich',
                    'price' => 50,
                    'description' => 'A spiced vegetable toast sandwich grilled with a flavourful masala mix.',
                    'ingredients' => ['Spiced Vegetable Mash', 'Onion', 'Masala Seasoning', 'Toasted Bread'],
                ],
                [
                    'name' => 'Veg Toast Sandwich',
                    'price' => 50,
                    'description' => 'A simple vegetable toast sandwich, grilled golden and lightly seasoned.',
                    'ingredients' => ['Mixed Vegetables', 'Butter', 'Toasted Bread'],
                ],
                [
                    'name' => 'Masala Noodle Toast Sandwich',
                    'price' => 60,
                    'description' => 'A fun fusion toast sandwich stuffed with spiced noodles and grilled until crisp.',
                    'ingredients' => ['Spiced Noodles', 'Mixed Vegetables', 'Toasted Bread'],
                ],
            ],
            'Fries' => [
                [
                    'name' => 'French Fries',
                    'price' => 60,
                    'description' => 'Classic golden, crispy French fries, lightly salted.',
                    'ingredients' => ['Potato', 'Salt'],
                ],
                [
                    'name' => 'Mayo Fries',
                    'price' => 70,
                    'description' => 'Crispy golden fries served with a generous drizzle of creamy mayonnaise.',
                    'ingredients' => ['Potato', 'Creamy Mayonnaise'],
                ],
                [
                    'name' => 'Peri Peri Fries',
                    'price' => 70,
                    'description' => 'Crispy fries tossed in a bold, tangy peri-peri seasoning for a spicy kick.',
                    'ingredients' => ['Potato', 'Peri-Peri Seasoning'],
                ],
                [
                    'name' => 'Cheesy Fries',
                    'price' => 80,
                    'description' => 'Golden fries loaded with a generous layer of melted cheese.',
                    'ingredients' => ['Potato', 'Melted Cheese'],
                ],
                [
                    'name' => 'Special Fries',
                    'price' => 80,
                    'description' => 'Our signature fries tossed in a special blend of seasonings and sauces.',
                    'ingredients' => ['Potato', 'Signature Seasoning Blend', 'Special Sauce'],
                ],
            ],
            'Vada Pav' => [
                [
                    'name' => 'Vada Pav',
                    'price' => 10,
                    'description' => "Mumbai's favourite street food — a spiced potato vada tucked in a soft pav with tangy chutneys.",
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Green Chutney', 'Garlic Chutney'],
                ],
                [
                    'name' => 'Special Vada Pav',
                    'price' => 15,
                    'description' => 'Our signature take on vada pav, loaded with extra chutneys and toppings.',
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Signature Chutneys'],
                ],
                [
                    'name' => 'Mayo Vada Pav',
                    'price' => 15,
                    'description' => 'A classic vada pav given a creamy twist with a layer of mayonnaise.',
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Creamy Mayonnaise'],
                ],
                [
                    'name' => 'Schezwan Vada Pav',
                    'price' => 15,
                    'description' => 'A fusion vada pav tossed with spicy, tangy schezwan sauce.',
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Schezwan Sauce'],
                ],
                [
                    'name' => 'Garlic Vada Pav',
                    'price' => 20,
                    'description' => 'A classic vada pav packed with a punchy garlic chutney kick.',
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Garlic Chutney'],
                ],
                [
                    'name' => 'Cheese Garlic Vada Pav',
                    'price' => 30,
                    'description' => 'A loaded vada pav combining melted cheese with a bold garlic chutney.',
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Melted Cheese', 'Garlic Chutney'],
                ],
                [
                    'name' => 'Cheese Vada Pav',
                    'price' => 25,
                    'description' => 'A classic vada pav topped with a generous layer of melted cheese.',
                    'ingredients' => ['Spiced Potato Vada', 'Soft Pav', 'Melted Cheese'],
                ],
                [
                    'name' => 'Grill Vada Pav',
                    'price' => 15,
                    'description' => 'A soft pav and spiced vada, toasted and grilled for a crisp, warm bite.',
                    'ingredients' => ['Spiced Potato Vada', 'Grilled Soft Pav', 'Chutney'],
                ],
                [
                    'name' => 'Tawa Butter Vada Pav',
                    'price' => 20,
                    'description' => 'A soft pav toasted in butter on a hot tawa, served with a spiced vada for a rich, indulgent bite.',
                    'ingredients' => ['Spiced Potato Vada', 'Butter-Toasted Pav', 'Chutney'],
                ],
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
                $slug = Str::slug($item['name']);

                $menuItem = MenuItem::factory()->create([
                    'menu_category_id' => $category->id,
                    'name' => $item['name'],
                    'slug' => $slug,
                    'description' => $item['description'],
                    'ingredients' => $item['ingredients'],
                    'base_price' => $item['price'],
                    // Temporary sourced photo — swap the file at this same path with real
                    // OpenCafe photography later; no code change needed when you do.
                    'image_path' => "images/menu/{$slug}.jpg",
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
