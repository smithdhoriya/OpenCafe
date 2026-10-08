<?php

use App\Actions\Orders\CreateOrderFromCart;
use App\Enums\OrderType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Combo;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

test('a guest cannot add a combo to the cart', function () {
    $combo = Combo::factory()->create();

    $response = Volt::test('customer.offers.index')
        ->call('addComboToCart', $combo->id);

    $response->assertRedirect(route('login'));
    expect(CartItem::count())->toBe(0);
});

test('an authenticated customer can add a combo to the cart', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create(['name' => 'Breakfast Bundle', 'price' => 199]);

    Volt::actingAs($user)->test('customer.offers.index')
        ->call('addComboToCart', $combo->id);

    $cart = Cart::activeForUser($user);
    expect($cart->items)->toHaveCount(1);

    $item = $cart->items->first();
    expect($item->combo_id)->toBe($combo->id);
    expect($item->menu_item_id)->toBeNull();
    expect((float) $item->unit_price)->toBe(199.0);
});

test('adding the same combo again increments its quantity instead of duplicating the line', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create();

    Volt::actingAs($user)->test('customer.offers.index')->call('addComboToCart', $combo->id);
    Volt::actingAs($user)->test('customer.offers.index')->call('addComboToCart', $combo->id);

    $cart = Cart::activeForUser($user);
    expect($cart->items)->toHaveCount(1);
    expect($cart->items->first()->quantity)->toBe(2);
});

test('an unavailable combo cannot be added to the cart', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->unavailable()->create();

    Volt::actingAs($user)->test('customer.offers.index')->call('addComboToCart', $combo->id);

    expect(CartItem::count())->toBe(0);
});

test('a combo scheduled to start in the future cannot be added to the cart', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create(['starts_at' => now()->addDay()]);

    Volt::actingAs($user)->test('customer.offers.index')->call('addComboToCart', $combo->id);

    expect(CartItem::count())->toBe(0);
});

test('a combo appears correctly on the cart page', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create(['name' => 'Lunch Combo', 'price' => 249]);
    Cart::firstOrCreateForUser($user)->addCombo($combo, 2);

    $this->actingAs($user)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertSee('Lunch Combo')
        ->assertSee('Combo');
});

test('a combo cart line quantity can be changed and removed like a normal line', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create();
    $cartItem = Cart::firstOrCreateForUser($user)->addCombo($combo, 1);

    $component = Volt::actingAs($user)->test('customer.cart.index')
        ->call('incrementItem', $cartItem->id);

    expect($cartItem->refresh()->quantity)->toBe(2);

    $component->call('removeItem', $cartItem->id);

    expect(CartItem::query()->whereKey($cartItem->id)->exists())->toBeFalse();
});

test('normal menu-item cart behavior is unaffected by combo support', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['base_price' => 120]);

    Volt::actingAs($user)->test('customer.menu.show', ['menuItem' => $menuItem])->call('addToCart');

    $cart = Cart::activeForUser($user);
    expect($cart->items)->toHaveCount(1);
    expect($cart->items->first()->menu_item_id)->toBe($menuItem->id);
    expect($cart->items->first()->combo_id)->toBeNull();
});

test('a cart with a mix of menu items and a combo checks out into one order', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['base_price' => 100]);
    $combo = Combo::factory()->create(['price' => 150]);
    $cart = Cart::firstOrCreateForUser($user);
    $cart->addItem($menuItem, null, 1);
    $cart->addCombo($combo, 1);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    expect((float) $order->subtotal)->toBe(250.0);
    expect(OrderItem::where('order_id', $order->id)->count())->toBe(2);
    expect(OrderItem::where('order_id', $order->id)->where('combo_id', $combo->id)->exists())->toBeTrue();
    expect(OrderItem::where('order_id', $order->id)->where('menu_item_id', $menuItem->id)->exists())->toBeTrue();
});

test('ordering a combo freezes its name, price and components into the order item snapshot', function () {
    $user = User::factory()->create();
    $itemA = MenuItem::factory()->create(['name' => 'Cold Brew']);
    $itemB = MenuItem::factory()->create(['name' => 'Avocado Toast']);
    $combo = Combo::factory()->create(['name' => 'Brunch Combo', 'price' => 299]);
    $combo->menuItems()->attach($itemA->id, ['quantity' => 1]);
    $combo->menuItems()->attach($itemB->id, ['quantity' => 2]);

    Cart::firstOrCreateForUser($user)->addCombo($combo, 1);

    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);

    $orderItem = OrderItem::where('order_id', $order->id)->where('combo_id', $combo->id)->first();
    expect($orderItem->item_name)->toBe('Brunch Combo');
    expect((float) $orderItem->unit_price)->toBe(299.0);
    expect($orderItem->combo_components_snapshot)->toHaveCount(2);

    $names = collect($orderItem->combo_components_snapshot)->pluck('menu_item_name');
    expect($names)->toContain('Cold Brew');
    expect($names)->toContain('Avocado Toast');
});

test('a historical combo order snapshot survives the combo later being edited', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['name' => 'Original Item']);
    $combo = Combo::factory()->create(['name' => 'Original Combo Name', 'price' => 199]);
    $combo->menuItems()->attach($menuItem->id, ['quantity' => 1]);

    Cart::firstOrCreateForUser($user)->addCombo($combo, 1);
    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);
    $orderItem = OrderItem::where('order_id', $order->id)->first();

    // The combo is renamed, repriced, and its composition changed after the order was placed.
    $combo->update(['name' => 'Renamed Combo', 'price' => 999]);
    $combo->menuItems()->sync([]);

    $orderItem->refresh();
    expect($orderItem->item_name)->toBe('Original Combo Name');
    expect((float) $orderItem->unit_price)->toBe(199.0);
    expect(collect($orderItem->combo_components_snapshot)->pluck('menu_item_name'))->toContain('Original Item');
});

test('a historical combo order snapshot survives the combo later being deleted', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create(['name' => 'Soon Deleted Combo', 'price' => 150]);
    $menuItem = MenuItem::factory()->create();
    $combo->menuItems()->attach($menuItem->id, ['quantity' => 1]);

    Cart::firstOrCreateForUser($user)->addCombo($combo, 1);
    $order = app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null);
    $orderItem = OrderItem::where('order_id', $order->id)->first();

    $combo->delete();

    $orderItem->refresh();
    expect($orderItem->item_name)->toBe('Soon Deleted Combo');
    expect((float) $orderItem->unit_price)->toBe(150.0);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Soon Deleted Combo');
});

test('a combo that becomes unavailable after being added to the cart cannot be ordered', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create();
    Cart::firstOrCreateForUser($user)->addCombo($combo, 1);

    $combo->update(['is_available' => false]);

    expect(fn () => app(CreateOrderFromCart::class)->handle($user, OrderType::Takeaway, null))
        ->toThrow(ValidationException::class);

    expect(Order::count())->toBe(0);
});

test('a customer can place an order containing a combo from the checkout page', function () {
    $user = User::factory()->create();
    $combo = Combo::factory()->create(['price' => 180]);
    Cart::firstOrCreateForUser($user)->addCombo($combo, 1);

    Volt::actingAs($user)->test('customer.checkout.index')
        ->call('placeOrder');

    expect(Order::count())->toBe(1);
    $order = Order::first();
    expect((float) $order->subtotal)->toBe(180.0);
    expect(OrderItem::where('order_id', $order->id)->where('combo_id', $combo->id)->exists())->toBeTrue();
});
