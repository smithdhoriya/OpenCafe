<?php

namespace Database\Seeders;

use App\Models\Offer;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Offer::factory()->percentage()->create([
            'code' => 'WELCOME10',
            'name' => 'Welcome Discount',
            'description' => '10% off your first order.',
            'value' => 10,
            'min_order_total' => 15,
            'max_discount_amount' => 10,
            'usage_limit' => 500,
            'usage_limit_per_user' => 1,
        ]);

        Offer::factory()->fixedAmount()->create([
            'code' => 'SAVE5',
            'name' => 'Five Off',
            'description' => '$5 off orders over $25.',
            'value' => 5,
            'min_order_total' => 25,
            'usage_limit' => 200,
            'usage_limit_per_user' => 2,
        ]);

        Offer::factory()->percentage()->create([
            'code' => 'STUDENT15',
            'name' => 'Student Discount',
            'description' => '15% off for students with a valid ID.',
            'value' => 15,
            'min_order_total' => null,
            'max_discount_amount' => 15,
            'usage_limit' => null,
            'usage_limit_per_user' => null,
        ]);

        Offer::factory()->percentage()->expired()->create([
            'code' => 'SUMMER20',
            'name' => 'Summer Sale',
            'description' => '20% off during the summer promotion.',
            'value' => 20,
            'min_order_total' => 20,
            'max_discount_amount' => 20,
            'is_active' => false,
        ]);
    }
}
