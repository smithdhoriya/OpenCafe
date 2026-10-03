<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = MenuItem::with('variantGroups.variants')->get();
        $customers = User::query()->where('role', UserRole::Customer)->get();

        $customers->random(min(5, $customers->count()))->each(
            fn (User $customer) => $this->buildCart($menuItems, ['user_id' => $customer->id])
        );

        $customers->random(min(3, $customers->count()))->each(
            fn (User $customer) => $this->buildCart($menuItems, ['user_id' => $customer->id], 'converted')
        );

        $customers->random(min(2, $customers->count()))->each(
            fn (User $customer) => $this->buildCart($menuItems, ['user_id' => $customer->id], 'abandoned')
        );

        collect(range(1, 2))->each(
            fn () => $this->buildCart($menuItems, [], 'guest')
        );
    }

    /**
     * @param  Collection<int, MenuItem>  $menuItems
     * @param  array<string, mixed>  $attributes
     */
    private function buildCart(Collection $menuItems, array $attributes, ?string $state = null): void
    {
        $factory = Cart::factory();

        if ($state !== null) {
            $factory = $factory->{$state}();
        }

        $cart = $factory->create($attributes);

        $menuItems->random(fake()->numberBetween(1, 4))->each(function (MenuItem $menuItem) use ($cart) {
            $variant = $menuItem->variantGroups->first()?->variants->first();

            $cart->items()->create([
                'menu_item_id' => $menuItem->id,
                'menu_item_variant_id' => $variant?->id,
                'quantity' => fake()->numberBetween(1, 3),
                'special_instructions' => fake()->optional()->sentence(),
            ]);
        });
    }
}
