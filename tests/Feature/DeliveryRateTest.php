<?php

use App\Models\DeliveryRate;

beforeEach(function () {
    DeliveryRate::factory()->create([
        'min_distance_km' => 0,
        'max_distance_km' => 2,
        'charge' => 20.00,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    DeliveryRate::factory()->create([
        'min_distance_km' => 2,
        'max_distance_km' => 5,
        'charge' => 40.00,
        'is_active' => true,
        'sort_order' => 1,
    ]);
});

test('0 km matches the first tier', function () {
    expect((float) DeliveryRate::forDistance(0)->charge)->toBe(20.0);
});

test('just below 2 km matches the first tier', function () {
    expect((float) DeliveryRate::forDistance(1.99)->charge)->toBe(20.0);
});

test('exactly 2 km matches the first tier, not the second', function () {
    expect((float) DeliveryRate::forDistance(2.0)->charge)->toBe(20.0);
});

test('just above 2 km matches the second tier', function () {
    expect((float) DeliveryRate::forDistance(2.01)->charge)->toBe(40.0);
});

test('exactly 5 km matches the second tier', function () {
    expect((float) DeliveryRate::forDistance(5.0)->charge)->toBe(40.0);
});

test('above 5 km has no matching tier', function () {
    expect(DeliveryRate::forDistance(5.01))->toBeNull();
});

test('inactive rates are never matched', function () {
    DeliveryRate::query()->update(['is_active' => false]);

    expect(DeliveryRate::forDistance(1.0))->toBeNull();
});

test('forDistance is deterministic across repeated calls', function () {
    $first = DeliveryRate::forDistance(1.5)->id;
    $second = DeliveryRate::forDistance(1.5)->id;

    expect($first)->toBe($second);
});
