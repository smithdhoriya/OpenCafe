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
            'Burgers',
            'Pizzas',
            'Frankie',
            'Coffee / Beverages',
            'Sandwich',
            'Fries',
            'Vada Pav',
        ];

        foreach ($categories as $index => $name) {
            MenuCategory::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => null,
                'sort_order' => $index,
            ]);
        }
    }
}
