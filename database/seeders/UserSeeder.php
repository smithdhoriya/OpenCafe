<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Ava Admin',
            'email' => 'admin@opencafe.test',
        ]);

        User::factory()->staff()->count(2)->create();

        User::factory()->count(12)->create();
    }
}
