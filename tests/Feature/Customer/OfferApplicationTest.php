<?php

use App\Actions\Orders\CreateOrderFromCart;
use App\Enums\OfferType;
use App\Enums\OrderType;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

test('an active percentage offer discounts the order subtotal', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    $offer = Offer::factory()->create([
        'code' => 'SAVE10',
        'type' => OfferType::Percentage,
        'value' => 10,
        'max_discount_amount' => null,
        'is_active' => true,
    ]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'SAVE10');

    expect((float) $order->discount_total)->toBe(20.0);
    expect((float) $order->total)->toBe(180.0);
    expect($order->offer_id)->toBe($offer->id);
    expect($order->offer_code)->toBe('SAVE10');
});

test('an active fixed amount offer discounts the order by a flat amount', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create([
        'code' => 'FLAT50',
        'type' => OfferType::FixedAmount,
        'value' => 50,
        'max_discount_amount' => null,
        'is_active' => true,
    ]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'FLAT50');

    expect((float) $order->discount_total)->toBe(50.0);
    expect((float) $order->total)->toBe(150.0);
});

test('a fixed amount offer discount is capped so the total can never go negative', function () {
    $user = User::factory()->create();
    addCartItem($user, 30);
    Offer::factory()->create([
        'code' => 'BIGFLAT',
        'type' => OfferType::FixedAmount,
        'value' => 500,
        'max_discount_amount' => null,
        'min_order_total' => null,
        'is_active' => true,
    ]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'BIGFLAT');

    expect((float) $order->discount_total)->toBe(30.0);
    expect((float) $order->total)->toBe(0.0);
});

test('a percentage offer discount is capped by max_discount_amount', function () {
    $user = User::factory()->create();
    addCartItem($user, 1000);
    Offer::factory()->create([
        'code' => 'CAPPED',
        'type' => OfferType::Percentage,
        'value' => 50,
        'max_discount_amount' => 100,
        'is_active' => true,
    ]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'CAPPED');

    expect((float) $order->discount_total)->toBe(100.0);
});

test('an inactive offer cannot be applied', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create(['code' => 'OFFCODE', 'is_active' => false]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'OFFCODE'))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('an expired offer cannot be applied', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create([
        'code' => 'EXPIRED',
        'is_active' => true,
        'ends_at' => now()->subDay(),
    ]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'EXPIRED'))
        ->toThrow(ValidationException::class);
});

test('an offer that has not started yet cannot be applied', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create([
        'code' => 'FUTURE',
        'is_active' => true,
        'starts_at' => now()->addDay(),
    ]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'FUTURE'))
        ->toThrow(ValidationException::class);
});

test('an offer below its minimum order amount cannot be applied', function () {
    $user = User::factory()->create();
    addCartItem($user, 50);
    Offer::factory()->create([
        'code' => 'MINORDER',
        'is_active' => true,
        'min_order_total' => 100,
    ]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'MINORDER'))
        ->toThrow(ValidationException::class);
});

test('an offer that has reached its global usage limit cannot be applied', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create([
        'code' => 'LIMITED',
        'is_active' => true,
        'usage_limit' => 1,
        'times_used' => 1,
    ]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'LIMITED'))
        ->toThrow(ValidationException::class);
});

test('an offer that has reached a customers personal usage limit cannot be applied again by that customer', function () {
    $user = User::factory()->create();
    $offer = Offer::factory()->create([
        'code' => 'ONEEACH',
        'is_active' => true,
        'usage_limit_per_user' => 1,
    ]);
    Order::factory()->for($user)->create(['offer_id' => $offer->id, 'offer_code' => 'ONEEACH']);

    addCartItem($user, 200);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'ONEEACH'))
        ->toThrow(ValidationException::class);
});

test('a nonexistent offer code is rejected', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'DOESNOTEXIST'))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('placing an order without an offer code applies no discount', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, null);

    expect((float) $order->discount_total)->toBe(0.0);
    expect((float) $order->total)->toBe(200.0);
});

test('successfully applying an offer increments its usage counter', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    $offer = Offer::factory()->create(['code' => 'COUNTME', 'is_active' => true, 'times_used' => 0]);

    app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'COUNTME');

    expect($offer->refresh()->times_used)->toBe(1);
});

test('the offer code is matched case-insensitively', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create(['code' => 'MIXEDCASE', 'is_active' => true]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null, 'mixedcase');

    expect((float) $order->discount_total)->toBeGreaterThan(0);
});

test('a customer cannot manipulate the discount amount from the checkout page', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('offerCode', 'NOT-A-REAL-CODE');

    expect($component->instance()->discount)->toBe(0.0);
    expect($component->instance()->total)->toBe(200.0);
});

test('the checkout page previews a valid offers discount before the order is placed', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create(['code' => 'PREVIEW10', 'type' => OfferType::Percentage, 'value' => 10, 'max_discount_amount' => null, 'is_active' => true]);

    $component = Volt::actingAs($user)->test('customer.checkout.index')
        ->set('offerCode', 'PREVIEW10');

    expect($component->instance()->discount)->toBe(20.0);
    expect($component->instance()->total)->toBe(180.0);

    expect(Order::count())->toBe(0);
});

test('a customer can place an order with a valid offer code from the checkout page', function () {
    $user = User::factory()->create();
    addCartItem($user, 200);
    Offer::factory()->create(['code' => 'CHECKOUT10', 'type' => OfferType::Percentage, 'value' => 10, 'max_discount_amount' => null, 'is_active' => true]);

    Volt::actingAs($user)->test('customer.checkout.index')
        ->set('offerCode', 'CHECKOUT10')
        ->call('placeOrder');

    $order = Order::first();
    expect((float) $order->discount_total)->toBe(20.0);
    expect($order->offer_code)->toBe('CHECKOUT10');
});

test('delivery charge calculation is unaffected by an applied offer discount', function () {
    seedDeliveryConfig();
    $user = User::factory()->create();
    addCartItem($user, 150);
    $address = addressAtDistanceKm($user, 1.5);
    Offer::factory()->create(['code' => 'DELIVERYOK', 'type' => OfferType::Percentage, 'value' => 10, 'max_discount_amount' => null, 'is_active' => true]);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Delivery, $address->id, 'DELIVERYOK');

    expect((float) $order->delivery_charge)->toBe(20.0);
    expect((float) $order->discount_total)->toBe(15.0);
    expect((float) $order->total)->toBe(155.0);
});
