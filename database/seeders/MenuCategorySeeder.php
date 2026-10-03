<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Coffee', 'description' => 'Espresso-based drinks and brewed coffee.'],
            ['name' => 'Tea', 'description' => 'Hot and iced teas.'],
            ['name' => 'Cold Drinks', 'description' => 'Iced coffees, smoothies, and sodas.'],
            ['name' => 'Breakfast', 'description' => 'Morning plates served until noon.'],
            ['name' => 'Sandwiches', 'description' => 'Toasted sandwiches and wraps.'],
            ['name' => 'Pastries', 'description' => 'Freshly baked pastries and bread.'],
            ['name' => 'Desserts', 'description' => 'Sweet treats to finish your meal.'],
            ['name' => 'Specials', 'description' => 'Seasonal and limited-time offerings.'],
        ];

        foreach ($categories as $index => $category) {
            MenuCategory::factory()->create([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'sort_order' => $index,
            ]);
        }
    }
}
