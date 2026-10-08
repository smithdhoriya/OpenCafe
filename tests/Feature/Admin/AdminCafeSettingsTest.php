<?php

use App\Enums\SettingType;
use App\Models\CafeSetting;
use App\Models\DeliveryRate;
use App\Models\User;
use Livewire\Volt\Volt;

test('an admin can view the cafe settings page', function () {
    $admin = User::factory()->admin()->create();
    CafeSetting::create(['key' => 'cafe_name', 'value' => 'OpenCafe', 'type' => SettingType::String, 'group' => 'general', 'label' => 'Cafe Name']);

    $this->actingAs($admin)
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('OpenCafe');
});

test('a customer cannot access admin cafe settings', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

test('a staff user cannot access admin cafe settings', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.settings'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin cafe settings', function () {
    $this->get(route('admin.settings'))
        ->assertRedirect(route('login'));
});

test('an admin can update a string setting', function () {
    $admin = User::factory()->admin()->create();
    CafeSetting::create(['key' => 'cafe_name', 'value' => 'Old Name', 'type' => SettingType::String, 'group' => 'general', 'label' => 'Cafe Name']);

    Volt::actingAs($admin)->test('admin.settings')
        ->set('values.cafe_name', 'New Name')
        ->call('saveSettings');

    expect(CafeSetting::get('cafe_name'))->toBe('New Name');
});

test('an admin can update a boolean setting', function () {
    $admin = User::factory()->admin()->create();
    CafeSetting::create(['key' => 'delivery_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => 'delivery', 'label' => 'Delivery Enabled']);

    Volt::actingAs($admin)->test('admin.settings')
        ->set('toggles.delivery_enabled', false)
        ->call('saveSettings');

    expect(CafeSetting::get('delivery_enabled'))->toBeFalse();
});

test('an admin can update the cafe coordinates', function () {
    $admin = User::factory()->admin()->create();
    CafeSetting::create(['key' => 'cafe_latitude', 'value' => '20.2774598', 'type' => SettingType::Decimal, 'group' => 'delivery', 'label' => 'Cafe Latitude']);
    CafeSetting::create(['key' => 'cafe_longitude', 'value' => '72.8837796', 'type' => SettingType::Decimal, 'group' => 'delivery', 'label' => 'Cafe Longitude']);

    Volt::actingAs($admin)->test('admin.settings')
        ->set('values.cafe_latitude', '20.2774598')
        ->set('values.cafe_longitude', '72.8837796')
        ->call('saveSettings')
        ->assertHasNoErrors();

    expect((float) CafeSetting::get('cafe_latitude'))->toEqualWithDelta(20.2774598, 0.0000001);
    expect((float) CafeSetting::get('cafe_longitude'))->toEqualWithDelta(72.8837796, 0.0000001);
});

test('a numeric setting rejects a non-numeric value', function () {
    $admin = User::factory()->admin()->create();
    CafeSetting::create(['key' => 'min_order_for_delivery', 'value' => '100.00', 'type' => SettingType::Decimal, 'group' => 'delivery', 'label' => 'Minimum Order']);

    Volt::actingAs($admin)->test('admin.settings')
        ->set('values.min_order_for_delivery', 'not-a-number')
        ->call('saveSettings')
        ->assertHasErrors(['values.min_order_for_delivery']);

    expect(CafeSetting::get('min_order_for_delivery'))->toBe(100.00);
});

test('a json setting rejects invalid json', function () {
    $admin = User::factory()->admin()->create();
    CafeSetting::create(['key' => 'opening_hours', 'value' => '{"monday":"9-5"}', 'type' => SettingType::Json, 'group' => 'general', 'label' => 'Opening Hours']);

    Volt::actingAs($admin)->test('admin.settings')
        ->set('values.opening_hours', '{not valid json')
        ->call('saveSettings')
        ->assertHasErrors(['values.opening_hours']);

    expect(CafeSetting::get('opening_hours'))->toBe(['monday' => '9-5']);
});

test('an admin can view the delivery rate tiers', function () {
    $admin = User::factory()->admin()->create();
    DeliveryRate::factory()->create(['min_distance_km' => 0, 'max_distance_km' => 2, 'charge' => 20]);

    $this->actingAs($admin)
        ->get(route('admin.settings'))
        ->assertOk();
});

test('an admin can update a delivery rate charge', function () {
    $admin = User::factory()->admin()->create();
    $rate = DeliveryRate::factory()->create(['min_distance_km' => 0, 'max_distance_km' => 2, 'charge' => 20]);

    Volt::actingAs($admin)->test('admin.settings')
        ->call('startEditRate', $rate->id)
        ->set('rate_charge', '25.00')
        ->call('saveRate');

    expect((float) $rate->refresh()->charge)->toBe(25.00);
});

test('a delivery rate max distance must be greater than its min distance', function () {
    $admin = User::factory()->admin()->create();
    $rate = DeliveryRate::factory()->create(['min_distance_km' => 2, 'max_distance_km' => 5, 'charge' => 40]);

    Volt::actingAs($admin)->test('admin.settings')
        ->call('startEditRate', $rate->id)
        ->set('rate_max_distance_km', '1')
        ->call('saveRate')
        ->assertHasErrors(['rate_max_distance_km']);

    expect((float) $rate->refresh()->max_distance_km)->toBe(5.0);
});
