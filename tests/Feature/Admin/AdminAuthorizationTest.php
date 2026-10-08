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
