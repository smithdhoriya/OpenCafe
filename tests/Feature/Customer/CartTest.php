<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\MenuItemVariantGroup;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to login when adding a menu item to the cart', function () {
    $menuItem = MenuItem::factory()->create();

    $response = Volt::test('customer.menu.show', ['menuItem' => $menuItem])
        ->call('addToCart');

    $response->assertRedirect(route('login'));
    expect(CartItem::count())->toBe(0);
});

test('an authenticated customer can add a menu item to the cart', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create();

    Volt::actingAs($user)
        ->test('customer.menu.show', ['menuItem' => $menuItem])
        ->call('addToCart');

    $cart = Cart::activeForUser($user);

    expect($cart)->not->toBeNull();
    expect($cart->items)->toHaveCount(1);
    expect($cart->items->first())
        ->menu_item_id->toBe($menuItem->id)
        ->quantity->toBe(1);
});

test('adding the same menu item again increments its quantity instead of duplicating the line', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create();

    Volt::actingAs($user)->test('customer.menu.show', ['menuItem' => $menuItem])->call('addToCart');
    Volt::actingAs($user)->test('customer.menu.show', ['menuItem' => $menuItem])->call('addToCart');

    $cart = Cart::activeForUser($user);

    expect($cart->items)->toHaveCount(1);
    expect($cart->items->first()->quantity)->toBe(2);
});

test('different variant selections on the same menu item create separate cart lines', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $group = MenuItemVariantGroup::factory()->create(['menu_item_id' => $menuItem->id]);
    $small = MenuItemVariant::factory()->create(['menu_item_variant_group_id' => $group->id]);
    $large = MenuItemVariant::factory()->create(['menu_item_variant_group_id' => $group->id]);

    Volt::actingAs($user)
        ->test('customer.menu.show', ['menuItem' => $menuItem])
        ->call('selectSingleVariant', $group->id, $small->id)
        ->call('addToCart');

    Volt::actingAs($user)
        ->test('customer.menu.show', ['menuItem' => $menuItem])
        ->call('selectSingleVariant', $group->id, $large->id)
        ->call('addToCart');

    $cart = Cart::activeForUser($user);

    expect($cart->items)->toHaveCount(2);
    expect($cart->items->pluck('menu_item_variant_id')->sort()->values()->all())
        ->toBe(collect([$small->id, $large->id])->sort()->values()->all());
});

test('unavailable menu items cannot be added to the cart', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->unavailable()->create();

    Volt::actingAs($user)->test('customer.menu.show', ['menuItem' => $menuItem])->call('addToCart');

    expect(CartItem::count())->toBe(0);
});

test('the cart page shows the empty state when the customer has no cart items', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.cart.index')
        ->assertSee('Your cart is empty');
});

test('the cart page displays real cart items with correct pricing and subtotal', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create(['base_price' => 5.00]);
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $cart->items()->create(['menu_item_id' => $menuItem->id, 'quantity' => 3]);

    Volt::actingAs($user)
        ->test('customer.cart.index')
        ->assertSee($menuItem->name)
        ->assertSee('15.00');
});

test('quantity can be increased and decreased on the cart page, but not below one', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $item = $cart->items()->create(['menu_item_id' => $menuItem->id, 'quantity' => 1]);

    $component = Volt::actingAs($user)->test('customer.cart.index');

    $component->call('decrementItem', $item->id);
    expect($item->refresh()->quantity)->toBe(1);

    $component->call('incrementItem', $item->id);
    expect($item->refresh()->quantity)->toBe(2);

    $component->call('decrementItem', $item->id);
    expect($item->refresh()->quantity)->toBe(1);
});

test('removing a cart item deletes it', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $item = $cart->items()->create(['menu_item_id' => $menuItem->id, 'quantity' => 1]);

    Volt::actingAs($user)
        ->test('customer.cart.index')
        ->call('removeItem', $item->id);

    expect(CartItem::find($item->id))->toBeNull();
});

test('a customer cannot modify another customer\'s cart item', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $owner->id]);
    $item = $cart->items()->create(['menu_item_id' => $menuItem->id, 'quantity' => 1]);

    Volt::actingAs($attacker)
        ->test('customer.cart.index')
        ->call('removeItem', $item->id)
        ->call('incrementItem', $item->id);

    expect($item->refresh())->quantity->toBe(1);
});

test('the navbar cart indicator reflects the real cart item count', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);
    $cart->items()->create(['menu_item_id' => $menuItem->id, 'quantity' => 2]);
    $cart->items()->create(['menu_item_id' => MenuItem::factory()->create()->id, 'quantity' => 3]);

    Volt::actingAs($user)
        ->test('customer.cart-indicator')
        ->assertSee('5');
});
