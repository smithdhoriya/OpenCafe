<?php

use App\Actions\Orders\CreateOrderFromCart;
use App\Enums\OrderType;
use App\Models\CafeSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\DeliveryRate;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\CafeSettingSeeder;
use Database\Seeders\DeliveryRateSeeder;

test('delivery rate seeder creates the 0-2km and 2-5km tiers with no tier beyond 5km', function () {
    $this->seed(DeliveryRateSeeder::class);

    expect(DeliveryRate::count())->toBe(2);

    $firstTier = DeliveryRate::where('min_distance_km', 0)->first();
    expect($firstTier->max_distance_km)->toEqual('2.00');
    expect($firstTier->charge)->toEqual('20.00');

    $secondTier = DeliveryRate::where('min_distance_km', 2)->first();
    expect($secondTier->max_distance_km)->toEqual('5.00');
    expect($secondTier->charge)->toEqual('40.00');

    expect(DeliveryRate::where('max_distance_km', '>', 5)->exists())->toBeFalse();
    expect(DeliveryRate::whereNull('max_distance_km')->exists())->toBeFalse();
});

test('cafe setting seeder configures delivery as enabled with a minimum order and fixed cafe coordinates', function () {
    $this->seed(CafeSettingSeeder::class);

    expect(CafeSetting::where('key', 'delivery_enabled')->first()->value)->toBe('1');
    expect(CafeSetting::where('key', 'min_order_for_delivery')->first()->value)->toBe('100.00');
    expect(CafeSetting::where('key', 'cafe_latitude')->first())->not->toBeNull();
    expect(CafeSetting::where('key', 'cafe_longitude')->first())->not->toBeNull();
});

test('cafe setting seeder configures the real OpenCafe coordinates (Shree Swaminarayan High School)', function () {
    $this->seed(CafeSettingSeeder::class);

    expect((float) CafeSetting::get('cafe_latitude'))->toEqualWithDelta(20.27745978745547, 0.00001);
    expect((float) CafeSetting::get('cafe_longitude'))->toEqualWithDelta(72.88377956187833, 0.00001);
});

test('delivery distance is calculated from the seeded real cafe coordinates, not a stale demo location', function () {
    $this->seed(CafeSettingSeeder::class);
    $this->seed(DeliveryRateSeeder::class);

    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['base_price' => 150]);
    $cart = Cart::factory()->for($user)->create();
    CartItem::factory()->for($cart)->create([
        'menu_item_id' => $menuItem->id,
        'menu_item_variant_id' => null,
        'quantity' => 1,
    ]);

    // ~1.5km due north of the seeded cafe coordinates (111.194926645 km per
    // degree of latitude), so it must land in the 0-2km / ₹20 tier only if
    // the distance is actually measured from the real coordinates.
    $kmPerDegreeLatitude = 111.194926645;
    $address = CustomerAddress::factory()->for($user)->create([
        'latitude' => 20.27745978745547 + (1.5 / $kmPerDegreeLatitude),
        'longitude' => 72.88377956187833,
        'is_default' => true,
    ]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id);

    expect((float) $order->delivery_charge)->toBe(20.00);
});
