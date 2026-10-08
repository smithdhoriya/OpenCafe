<?php

use App\Models\Combo;
use App\Models\Offer;
use App\Models\User;

test('an admin can view the offers and combos page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.offers'))
        ->assertOk();
});

test('a customer cannot access admin offers and combos', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.offers'))
        ->assertForbidden();
});

test('a staff user cannot access admin offers and combos', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.offers'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin offers and combos', function () {
    $this->get(route('admin.offers'))
        ->assertRedirect(route('login'));
});

test('the page clearly states no offers or combos exist when there is no real data', function () {
    $admin = User::factory()->admin()->create();

    expect(Offer::count())->toBe(0);
    expect(Combo::count())->toBe(0);

    $this->actingAs($admin)
        ->get(route('admin.offers'))
        ->assertOk()
        ->assertSee('No offers exist yet')
        ->assertSee('No combo deals exist yet');
});

test('the page does not invent or seed fake offers or combos', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.offers'))->assertOk();

    expect(Offer::count())->toBe(0);
    expect(Combo::count())->toBe(0);
});
