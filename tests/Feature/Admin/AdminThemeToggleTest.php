<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

test('the admin layout defines both light and dark background and text colors on the body', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();
    $response->assertSee('bg-amber-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100', false);
});

test('every admin page listed for the dark-mode audit renders successfully for an admin', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['status' => OrderStatus::Pending]);

    foreach ([
        route('admin.dashboard'),
        route('admin.orders'),
        route('admin.orders.show', $order),
        route('admin.menu'),
        route('admin.categories'),
        route('admin.customers'),
        route('admin.offers'),
        route('admin.reviews'),
        route('admin.settings'),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }
});

test('the admin dashboard renders a light/dark theme toggle', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Toggle light and dark theme', false);
});

test('the admin sidebar still renders every management navigation link', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk();

    foreach ([
        'Dashboard',
        'Orders',
        'Menu',
        'Categories',
        'Customers',
        'Offers & Combos',
        'Reviews',
        'Cafe Settings',
        'AI Assistant',
    ] as $label) {
        $response->assertSee($label);
    }
});
