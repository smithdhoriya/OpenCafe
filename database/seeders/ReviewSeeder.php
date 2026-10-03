<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = MenuItem::all();
        $customers = User::query()->where('role', UserRole::Customer)->get();
        $moderator = User::query()->where('role', UserRole::Staff)->first();

        $menuItems->random(min(15, $menuItems->count()))->each(function (MenuItem $menuItem) use ($customers) {
            Review::factory()->create([
                'user_id' => $customers->random()->id,
                'menu_item_id' => $menuItem->id,
            ]);
        });

        Review::factory()->guest()->count(3)->create([
            'menu_item_id' => fn () => $menuItems->random()->id,
        ]);

        Review::factory()->pending()->count(2)->create([
            'user_id' => fn () => $customers->random()->id,
            'menu_item_id' => fn () => $menuItems->random()->id,
        ]);

        Review::factory()->rejected()->create([
            'user_id' => $customers->random()->id,
            'menu_item_id' => $menuItems->random()->id,
            'moderated_by' => $moderator?->id,
        ]);
    }
}
