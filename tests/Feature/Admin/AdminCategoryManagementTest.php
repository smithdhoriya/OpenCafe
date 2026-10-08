<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Livewire\Volt\Volt;

test('an admin can view the categories list', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create(['name' => 'Beverages']);

    $this->actingAs($admin)
        ->get(route('admin.categories'))
        ->assertOk()
        ->assertSee('Beverages');
});

test('a customer cannot access admin categories', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.categories'))
        ->assertForbidden();
});

test('a staff user cannot access admin categories', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.categories'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin categories', function () {
    $this->get(route('admin.categories'))
        ->assertRedirect(route('login'));
});

test('an admin can create a category', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.categories')
        ->call('startCreate')
        ->set('name', 'Desserts')
        ->set('slug', 'desserts')
        ->set('sort_order', 5)
        ->call('save');

    expect(MenuCategory::where('slug', 'desserts')->exists())->toBeTrue();
});

test('creating a category requires a name and a unique slug', function () {
    $admin = User::factory()->admin()->create();
    MenuCategory::factory()->create(['slug' => 'burgers']);

    Volt::actingAs($admin)->test('admin.categories')
        ->call('startCreate')
        ->set('name', '')
        ->set('slug', 'burgers')
        ->call('save')
        ->assertHasErrors(['name', 'slug']);

    expect(MenuCategory::count())->toBe(1);
});

test('an admin can edit an existing category', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    Volt::actingAs($admin)->test('admin.categories')
        ->call('startEdit', $category->id)
        ->set('name', 'New Name')
        ->call('save');

    expect($category->refresh()->name)->toBe('New Name');
});

test('editing a category keeps its own slug valid against the uniqueness rule', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create(['name' => 'Snacks', 'slug' => 'snacks']);

    Volt::actingAs($admin)->test('admin.categories')
        ->call('startEdit', $category->id)
        ->set('description', 'Updated description')
        ->call('save')
        ->assertHasNoErrors();

    expect($category->refresh()->description)->toBe('Updated description');
});

test('an admin can toggle category availability', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create(['is_active' => true]);

    Volt::actingAs($admin)->test('admin.categories')
        ->call('toggleActive', $category->id);

    expect($category->refresh()->is_active)->toBeFalse();
});

test('an admin cannot delete a category that still has menu items', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->create(['menu_category_id' => $category->id]);

    Volt::actingAs($admin)->test('admin.categories')
        ->call('delete', $category->id);

    expect(MenuCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('an admin can delete an empty category', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create();

    Volt::actingAs($admin)->test('admin.categories')
        ->call('delete', $category->id);

    expect(MenuCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});
