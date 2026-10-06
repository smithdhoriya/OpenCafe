<?php

use App\Models\CafeSetting;
use App\Models\DeliveryRate;
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
