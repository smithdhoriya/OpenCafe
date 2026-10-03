<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerAddressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->where('role', UserRole::Customer)->each(function (User $customer) {
            CustomerAddress::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($customer)
                ->sequence(fn ($sequence) => ['is_default' => $sequence->index === 0])
                ->create();
        });
    }
}
