<?php

use App\Enums\SettingType;
use App\Models\CafeSetting;

test('get returns the default when the key does not exist', function () {
    expect(CafeSetting::get('does_not_exist', 'fallback'))->toBe('fallback');
    expect(CafeSetting::get('does_not_exist'))->toBeNull();
});

test('get casts a decimal setting to a float', function () {
    CafeSetting::create([
        'key' => 'min_order_for_delivery',
        'value' => '100.00',
        'type' => SettingType::Decimal,
        'group' => 'delivery',
    ]);

    expect(CafeSetting::get('min_order_for_delivery'))->toBe(100.0);
});

test('get casts a boolean setting', function () {
    CafeSetting::create([
        'key' => 'delivery_enabled',
        'value' => '1',
        'type' => SettingType::Boolean,
        'group' => 'delivery',
    ]);

    expect(CafeSetting::get('delivery_enabled'))->toBeTrue();
});

test('get casts an integer setting', function () {
    CafeSetting::create([
        'key' => 'max_items_per_order',
        'value' => '12',
        'type' => SettingType::Integer,
        'group' => 'ordering',
    ]);

    expect(CafeSetting::get('max_items_per_order'))->toBe(12);
});

test('get casts a json setting to an array', function () {
    CafeSetting::create([
        'key' => 'opening_hours',
        'value' => json_encode(['monday_friday' => '07:00-18:00']),
        'type' => SettingType::Json,
        'group' => 'general',
    ]);

    expect(CafeSetting::get('opening_hours'))->toBe(['monday_friday' => '07:00-18:00']);
});

test('get returns a string setting as-is', function () {
    CafeSetting::create([
        'key' => 'cafe_name',
        'value' => 'OpenCafe',
        'type' => SettingType::String,
        'group' => 'general',
    ]);

    expect(CafeSetting::get('cafe_name'))->toBe('OpenCafe');
});

test('get returns the default when the stored value is null', function () {
    CafeSetting::create([
        'key' => 'description',
        'value' => null,
        'type' => SettingType::String,
        'group' => 'general',
    ]);

    expect(CafeSetting::get('description', 'none'))->toBe('none');
});
