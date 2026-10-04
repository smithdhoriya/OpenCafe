<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The physical OpenCafe menu card does not define any promo codes or
     * discounts, so no Offer records are seeded. Kept as a seeder (rather
     * than removed) so the offers feature stays wired up for when real
     * promotions are defined.
     */
    public function run(): void
    {
        //
    }
}
