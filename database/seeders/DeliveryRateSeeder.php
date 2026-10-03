<?php

namespace Database\Seeders;

use App\Models\DeliveryRate;
use Illuminate\Database\Seeder;

class DeliveryRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiers = [
            ['min' => 0, 'max' => 2, 'charge' => 2.00],
            ['min' => 2, 'max' => 5, 'charge' => 3.50],
            ['min' => 5, 'max' => 10, 'charge' => 5.00],
        ];

        foreach ($tiers as $sortOrder => $tier) {
            DeliveryRate::factory()->create([
                'min_distance_km' => $tier['min'],
                'max_distance_km' => $tier['max'],
                'charge' => $tier['charge'],
                'sort_order' => $sortOrder,
            ]);
        }

        DeliveryRate::factory()->openEnded()->create([
            'min_distance_km' => 10,
            'charge' => 7.50,
            'sort_order' => count($tiers),
        ]);
    }
}
