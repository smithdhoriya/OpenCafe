<?php

use App\Actions\Orders\CreateOrderFromCart;
use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

test('a takeaway order is created from the active cart with the correct totals', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    $cartItem = addCartItem($user, 150, 2);
    $cart = $cartItem->cart;

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    expect($order->user_id)->toBe($user->id);
    expect($order->type)->toBe(OrderType::Takeaway);
    expect($order->status)->toBe(OrderStatus::Pending);
    expect($order->payment_status)->toBe(PaymentStatus::Unpaid);
    expect($order->currency)->toBe('INR');
    expect((float) $order->subtotal)->toBe(300.0);
    expect((float) $order->discount_total)->toBe(0.0);
    expect((float) $order->tax_total)->toBe(0.0);
    expect((float) $order->delivery_charge)->toBe(0.0);
    expect((float) $order->total)->toBe(300.0);
    expect($order->order_number)->toStartWith('ORD-');

    expect(OrderDelivery::count())->toBe(0);
    expect($cart->refresh()->status)->toBe(CartStatus::Converted);
});

test('order items snapshot the cart item name, variant, price and quantity', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['name' => 'Cold Brew', 'base_price' => 120]);
    $cart = Cart::factory()->for($user)->create();
    CartItem::factory()->for($cart)->create([
        'menu_item_id' => $menuItem->id,
        'menu_item_variant_id' => null,
        'quantity' => 3,
    ]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    expect(OrderItem::count())->toBe(1);
    $item = OrderItem::first();
    expect($item->order_id)->toBe($order->id);
    expect($item->menu_item_id)->toBe($menuItem->id);
    expect($item->item_name)->toBe('Cold Brew');
    expect($item->variant_name)->toBeNull();
    expect((float) $item->unit_price)->toBe(120.0);
    expect($item->quantity)->toBe(3);
    expect((float) $item->line_total)->toBe(360.0);
});

test('cart items remain intact and a new active cart can be used after conversion', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    $cartItem = addCartItem($user, 150);
    $cart = $cartItem->cart;

    app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    expect($cart->items()->count())->toBe(1);
    expect($cart->refresh()->status)->toBe(CartStatus::Converted);

    $newCart = Cart::firstOrCreateForUser($user);
    expect($newCart->id)->not->toBe($cart->id);
    expect($newCart->status)->toBe(CartStatus::Active);
});

test('a delivery order within 0 to 2 km is charged 20 rupees and snapshots the address', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1.5);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id);

    expect((float) $order->delivery_charge)->toBe(20.0);
    expect((float) $order->total)->toBe(170.0);

    $delivery = OrderDelivery::where('order_id', $order->id)->first();
    expect($delivery)->not->toBeNull();
    expect($delivery->customer_address_id)->toBe($address->id);
    expect($delivery->recipient_name)->toBe($address->recipient_name);
    expect($delivery->address_line_1)->toBe($address->address_line_1);
    expect((float) $delivery->delivery_charge)->toBe(20.0);
    expect((float) $delivery->distance_km)->toBeGreaterThan(1.4)->toBeLessThan(1.6);
    expect($delivery->delivery_rate_id)->not->toBeNull();
});

test('a delivery order within 2 to 5 km is charged 40 rupees', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 3.5);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id);

    expect((float) $order->delivery_charge)->toBe(40.0);
    expect((float) $order->total)->toBe(190.0);
});

test('delivery is rejected when delivery is disabled', function () {
    seedDeliveryConfig(deliveryEnabled: false);
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('delivery is rejected when the subtotal is below the minimum', function () {
    seedDeliveryConfig(minOrderForDelivery: 100);
    $user = User::factory()->create();
    addCartItem($user, 50);
    $address = addressAtDistanceKm($user, 1);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('delivery is rejected without a valid selected address', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, null))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('delivery is rejected beyond 5 km', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 10);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
    expect(OrderDelivery::count())->toBe(0);
});

test('a customer cannot place a delivery order using another customers address', function () {
    seedDeliveryConfig();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    addCartItem($intruder, 150);
    $othersAddress = addressAtDistanceKm($owner, 1);

    expect(fn () => app(CreateOrderFromCart::class)->handle($intruder, OrderType::Delivery, $othersAddress->id))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('an empty cart cannot create an order', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    Cart::factory()->for($user)->create();

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('a cart item for an unavailable menu item cannot create an order', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->unavailable()->create();
    $cart = Cart::factory()->for($user)->create();
    CartItem::factory()->for($cart)->create(['menu_item_id' => $menuItem->id, 'menu_item_variant_id' => null]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
    expect($cart->refresh()->status)->toBe(CartStatus::Active);
});

test('calling the action again after conversion does not create a duplicate order', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);

    app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(1);
});

test('a customer can place a takeaway order from the checkout page', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->call('placeOrder');

    expect(Order::count())->toBe(1);
    $order = Order::first();
    $component->assertRedirect(route('orders.show', $order));
});

test('a customer can place a delivery order from the checkout page', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1.5);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('fulfillmentType', 'delivery')
        ->set('selectedAddressId', $address->id)
        ->call('placeOrder');

    expect(Order::count())->toBe(1);
    $order = Order::first();
    expect((float) $order->delivery_charge)->toBe(20.0);
    $component->assertRedirect(route('orders.show', $order));
});

test('submitting the checkout place order action twice does not create two orders', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->call('placeOrder');

    expect(Order::count())->toBe(1);

    // Simulate a resubmission reaching the server after the first one already
    // converted the cart (e.g. a double-click or back-button replay).
    $component->call('placeOrder');

    expect(Order::count())->toBe(1);
});
