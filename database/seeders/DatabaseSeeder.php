<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            CafeSettingSeeder::class,
            MenuCategorySeeder::class,
            MenuItemSeeder::class,
            OfferSeeder::class,
            ComboSeeder::class,
            DeliveryRateSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                UserSeeder::class,
                CustomerAddressSeeder::class,
                CartSeeder::class,
                OrderSeeder::class,
                ReviewSeeder::class,
            ]);
        }
    }
}
