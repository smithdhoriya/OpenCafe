<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

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

test('previewing and adjusting checkout without placing the order never creates order records', function () {
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
