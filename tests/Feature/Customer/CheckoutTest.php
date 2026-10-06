<?php

use App\Enums\SettingType;
use App\Models\CafeSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerAddress;
use App\Models\DeliveryRate;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

function seedDeliveryConfig(bool $deliveryEnabled = true, float $minOrderForDelivery = 100.0): void
{
    CafeSetting::create(['key' => 'delivery_enabled', 'value' => $deliveryEnabled ? '1' : '0', 'type' => SettingType::Boolean, 'group' => 'delivery']);
    CafeSetting::create(['key' => 'min_order_for_delivery', 'value' => (string) $minOrderForDelivery, 'type' => SettingType::Decimal, 'group' => 'delivery']);
    CafeSetting::create(['key' => 'cafe_latitude', 'value' => '12.9716', 'type' => SettingType::Decimal, 'group' => 'delivery']);
    CafeSetting::create(['key' => 'cafe_longitude', 'value' => '77.5946', 'type' => SettingType::Decimal, 'group' => 'delivery']);

    DeliveryRate::factory()->create(['min_distance_km' => 0, 'max_distance_km' => 2, 'charge' => 20, 'is_active' => true, 'sort_order' => 0]);
    DeliveryRate::factory()->create(['min_distance_km' => 2, 'max_distance_km' => 5, 'charge' => 40, 'is_active' => true, 'sort_order' => 1]);
}

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

function addressAtDistanceKm(User $user, float $km, bool $default = true): CustomerAddress
{
    // One degree of latitude is an exact great-circle arc of R * deg2rad(1) km,
    // so offsetting latitude only gives a precisely known distance from the cafe.
    $kmPerDegreeLatitude = 111.194926645;

    return CustomerAddress::factory()->for($user)->create([
        'latitude' => 12.9716 + ($km / $kmPerDegreeLatitude),
        'longitude' => 77.5946,
        'is_default' => $default,
    ]);
}

test('guests are redirected to login when visiting checkout', function () {
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
});

test('a customer with an empty cart is redirected back to the cart page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.index'))
        ->assertRedirect(route('cart.index'));
});

test('checkout displays the cart items and subtotal', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['name' => 'Cappuccino', 'base_price' => 150]);
    $cart = Cart::factory()->for($user)->create();
    CartItem::factory()->for($cart)->create(['menu_item_id' => $menuItem->id, 'menu_item_variant_id' => null, 'quantity' => 2]);

    $component = Volt::actingAs($user)->test('customer.checkout.index');

    $component->assertOk()->assertSee('Cappuccino');
    expect($component->instance()->subtotal)->toBe(300.0);
});

test('takeaway has a zero delivery charge and totals match the subtotal', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->assertSet('fulfillmentType', 'takeaway');

    expect($component->instance()->deliveryCharge)->toBe(0.0);
    expect($component->instance()->total)->toBe(150.0);
});

test('delivery is unavailable when delivery_enabled is off', function () {
    seedDeliveryConfig(deliveryEnabled: false);
    $user = User::factory()->create();
    addCartItem($user, 150);
    addressAtDistanceKm($user, 1);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery');

    expect($component->instance()->deliveryEnabled)->toBeFalse();
    expect($component->instance()->deliveryAvailable)->toBeFalse();
    expect($component->instance()->deliveryCharge)->toBe(0.0);
    expect($component->instance()->canCheckout)->toBeFalse();
});

test('delivery is blocked when the cart subtotal is below the configured minimum', function () {
    seedDeliveryConfig(minOrderForDelivery: 100);
    $user = User::factory()->create();
    addCartItem($user, 50);
    addressAtDistanceKm($user, 1);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery');

    expect($component->instance()->meetsMinimumForDelivery)->toBeFalse();
    expect($component->instance()->deliveryAvailable)->toBeFalse();
    expect($component->instance()->canCheckout)->toBeFalse();
});

test('delivery requires at least one saved address', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery');

    expect($component->instance()->addresses)->toBeEmpty();
    expect($component->instance()->selectedAddress)->toBeNull();
    expect($component->instance()->deliveryAvailable)->toBeFalse();
    expect($component->instance()->canCheckout)->toBeFalse();
});

test("the customer's default address is preselected", function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $other = addressAtDistanceKm($user, 3, default: false);
    $default = addressAtDistanceKm($user, 1, default: true);

    $component = Volt::actingAs($user)->test('customer.checkout.index');

    $component->assertSet('selectedAddressId', $default->id);
});

test('a customer cannot select another customers address', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    addCartItem($intruder, 150);
    $othersAddress = addressAtDistanceKm($user, 1);

    $component = Volt::actingAs($intruder)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $othersAddress->id);

    expect($component->instance()->selectedAddress)->toBeNull();
    expect($component->instance()->deliveryCharge)->toBe(0.0);
});

test('the delivery distance is calculated from the selected address and cafe coordinates', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1.5);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id);

    expect($component->instance()->deliveryDistanceKm)->toBeGreaterThan(1.4)
        ->toBeLessThan(1.6);
});

test('a delivery within 0 to 2 km is charged 20 rupees', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1.5);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id);

    expect($component->instance()->deliveryCharge)->toBe(20.0);
    expect($component->instance()->total)->toBe(170.0);
});

test('a delivery within 2 to 5 km is charged 40 rupees', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 3.5);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id);

    expect($component->instance()->deliveryCharge)->toBe(40.0);
    expect($component->instance()->total)->toBe(190.0);
});

test('delivery beyond 5 km is unavailable', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 10);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id);

    expect($component->instance()->deliveryRate)->toBeNull();
    expect($component->instance()->deliveryAvailable)->toBeFalse();
    expect($component->instance()->deliveryCharge)->toBe(0.0);
    expect($component->instance()->canCheckout)->toBeFalse();
});

test('the final checkout total adds the delivery charge to the subtotal', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 200, 2);
    $address = addressAtDistanceKm($user, 1);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id);

    expect($component->instance()->subtotal)->toBe(400.0);
    expect($component->instance()->deliveryCharge)->toBe(20.0);
    expect($component->instance()->total)->toBe(420.0);
});

test('visiting and interacting with checkout never creates order records', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1.5);

    Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id);

    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
    expect(OrderDelivery::count())->toBe(0);
});
