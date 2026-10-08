<?php

use App\Models\Combo;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;

test('an admin can view combos on the offers and combos page', function () {
    $admin = User::factory()->admin()->create();
    Combo::factory()->create(['name' => 'Breakfast Bundle']);

    $component = Volt::actingAs($admin)->test('admin.offers')
        ->set('activeTab', 'combos');

    $component->assertSee('Breakfast Bundle');
});

test('a customer cannot access admin combo management', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.offers'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin combo management', function () {
    $this->get(route('admin.offers'))
        ->assertRedirect(route('login'));
});

test('an admin can create a combo with real menu items', function () {
    $admin = User::factory()->admin()->create();
    $item1 = MenuItem::factory()->create(['name' => 'Cheese Burger']);
    $item2 = MenuItem::factory()->create(['name' => 'French Fries']);

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateCombo')
        ->set('combo_name', 'Burger Meal')
        ->set('combo_slug', 'burger-meal')
        ->set('combo_price', '199.00')
        ->set('comboItemMenuItemId', $item1->id)
        ->set('comboItemQuantity', 1)
        ->call('addComboItem')
        ->set('comboItemMenuItemId', $item2->id)
        ->set('comboItemQuantity', 2)
        ->call('addComboItem')
        ->call('saveCombo')
        ->assertHasNoErrors();

    $combo = Combo::where('slug', 'burger-meal')->first();
    expect($combo)->not->toBeNull();
    expect($combo->menuItems)->toHaveCount(2);

    $friesPivot = $combo->menuItems->firstWhere('id', $item2->id);
    expect($friesPivot->pivot->quantity)->toBe(2);
});

test('a combo requires at least one menu item', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateCombo')
        ->set('combo_name', 'Empty Combo')
        ->set('combo_slug', 'empty-combo')
        ->set('combo_price', '99.00')
        ->call('saveCombo')
        ->assertHasErrors(['comboItems']);

    expect(Combo::count())->toBe(0);
});

test('adding the same menu item twice merges into one line with combined quantity', function () {
    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create();

    $component = Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateCombo')
        ->set('comboItemMenuItemId', $item->id)
        ->set('comboItemQuantity', 1)
        ->call('addComboItem')
        ->set('comboItemMenuItemId', $item->id)
        ->set('comboItemQuantity', 2)
        ->call('addComboItem');

    expect($component->get('comboItems'))->toHaveCount(1);
    expect($component->get('comboItems')[0]['quantity'])->toBe(3);
});

test('invalid combo field values are rejected', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateCombo')
        ->set('combo_name', '')
        ->set('combo_slug', '')
        ->set('combo_price', '')
        ->call('saveCombo')
        ->assertHasErrors(['combo_name', 'combo_slug', 'combo_price']);
});

test('duplicate combo slugs are rejected', function () {
    $admin = User::factory()->admin()->create();
    Combo::factory()->create(['slug' => 'taken-slug']);
    $item = MenuItem::factory()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateCombo')
        ->set('combo_name', 'New Combo')
        ->set('combo_slug', 'taken-slug')
        ->set('combo_price', '50')
        ->set('comboItemMenuItemId', $item->id)
        ->call('addComboItem')
        ->call('saveCombo')
        ->assertHasErrors(['combo_slug']);
});

test('an admin can edit an existing combo and change its items', function () {
    $admin = User::factory()->admin()->create();
    $combo = Combo::factory()->create(['name' => 'Old Combo', 'price' => 100]);
    $originalItem = MenuItem::factory()->create();
    $combo->menuItems()->attach($originalItem->id, ['quantity' => 1]);
    $newItem = MenuItem::factory()->create(['name' => 'Replacement Item']);

    $component = Volt::actingAs($admin)->test('admin.offers')
        ->call('startEditCombo', $combo->id);

    expect($component->get('comboItems'))->toHaveCount(1);

    $component
        ->call('removeComboItem', 0)
        ->set('comboItemMenuItemId', $newItem->id)
        ->set('comboItemQuantity', 1)
        ->call('addComboItem')
        ->set('combo_price', '150.00')
        ->call('saveCombo')
        ->assertHasNoErrors();

    $combo->refresh();
    expect((float) $combo->price)->toBe(150.0);
    expect($combo->menuItems->pluck('id')->all())->toBe([$newItem->id]);
});

test('an admin can activate and deactivate a combo', function () {
    $admin = User::factory()->admin()->create();
    $combo = Combo::factory()->create(['is_available' => true]);

    Volt::actingAs($admin)->test('admin.offers')
        ->call('toggleComboAvailable', $combo->id);

    expect($combo->refresh()->is_available)->toBeFalse();
});

test('an admin can delete a combo', function () {
    $admin = User::factory()->admin()->create();
    $combo = Combo::factory()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('deleteCombo', $combo->id);

    expect(Combo::query()->whereKey($combo->id)->exists())->toBeFalse();
});

test('a combo photo upload is stored under the existing public images path', function () {
    File::ensureDirectoryExists(public_path('images/combos'));

    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create();

    Volt::actingAs($admin)->test('admin.offers')
        ->call('startCreateCombo')
        ->set('combo_name', 'Photo Combo')
        ->set('combo_slug', 'photo-combo')
        ->set('combo_price', '120')
        ->set('comboItemMenuItemId', $item->id)
        ->call('addComboItem')
        ->set('combo_image', UploadedFile::fake()->create('combo.jpg', 50, 'image/jpeg'))
        ->call('saveCombo');

    $combo = Combo::where('slug', 'photo-combo')->first();
    expect($combo->image_path)->toStartWith('images/combos/photo-combo-');
    expect(File::exists(public_path($combo->image_path)))->toBeTrue();

    File::delete(public_path($combo->image_path));
});
