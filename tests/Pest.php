<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Enums\SettingType;
use App\Models\CafeSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\DeliveryRate;
use App\Models\MenuItem;
use App\Models\User;

/**
 * Seed the delivery configuration (0-2km => ₹20, 2-5km => ₹40) used by checkout
 * and order-creation tests, fixed around the same cafe coordinates used by
 * addressAtDistanceKm().
 */
function seedDeliveryConfig(bool $deliveryEnabled = true, float $minOrderForDelivery = 100.0): void
{
    CafeSetting::create(['key' => 'delivery_enabled', 'value' => $deliveryEnabled ? '1' : '0', 'type' => SettingType::Boolean, 'group' => 'delivery']);
    CafeSetting::create(['key' => 'min_order_for_delivery', 'value' => (string) $minOrderForDelivery, 'type' => SettingType::Decimal, 'group' => 'delivery']);
    CafeSetting::create(['key' => 'cafe_latitude', 'value' => '12.9716', 'type' => SettingType::Decimal, 'group' => 'delivery']);
    CafeSetting::create(['key' => 'cafe_longitude', 'value' => '77.5946', 'type' => SettingType::Decimal, 'group' => 'delivery']);

    DeliveryRate::factory()->create(['min_distance_km' => 0, 'max_distance_km' => 2, 'charge' => 20, 'is_active' => true, 'sort_order' => 0]);
    DeliveryRate::factory()->create(['min_distance_km' => 2, 'max_distance_km' => 5, 'charge' => 40, 'is_active' => true, 'sort_order' => 1]);
}

/**
 * Create an active cart with a single line item for the given user.
 */
function addCartItem(User $user, float $price, int $quantity = 1): CartItem
{
    $menuItem = MenuItem::factory()->create(['base_price' => $price]);
    $cart = Cart::factory()->for($user)->create();

    return CartItem::factory()->for($cart)->create([
        'menu_item_id' => $menuItem->id,
        'menu_item_variant_id' => null,
        'quantity' => $quantity,
    ]);
}

/**
 * Create a saved address at a precisely known distance from the cafe coordinates
 * seeded by seedDeliveryConfig() — one degree of latitude is an exact great-circle
 * arc of R * deg2rad(1) km, so offsetting latitude only gives an exact distance.
 */
function addressAtDistanceKm(User $user, float $km, bool $default = true): CustomerAddress
{
    $kmPerDegreeLatitude = 111.194926645;

    return CustomerAddress::factory()->for($user)->create([
        'latitude' => 12.9716 + ($km / $kmPerDegreeLatitude),
        'longitude' => 77.5946,
        'is_default' => $default,
    ]);
}
