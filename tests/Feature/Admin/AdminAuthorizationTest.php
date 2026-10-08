<?php

use App\Models\User;

test('an admin can access a protected admin route', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('a normal customer cannot access a protected admin route', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('a staff user cannot access a protected admin route', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('a guest cannot access a protected admin route', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('the admin dashboard renders the overview shell for an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Total Orders')
        ->assertSee('Pending Orders')
        ->assertSee('Menu Items')
        ->assertSee('Customers')
        ->assertSee('Log Out');
});

test('the AI assistant admin route is still a clearly-labeled coming-soon foundation', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.ai-assistant'))
        ->assertOk()
        ->assertSee('coming soon');
});

test('customer-facing routes remain accessible after the admin foundation changes', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('menu.index'))->assertOk();

    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertOk();
});
