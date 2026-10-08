<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;

test('an admin can view the menu items list', function () {
    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create(['name' => 'Cold Brew']);

    $this->actingAs($admin)
        ->get(route('admin.menu'))
        ->assertOk()
        ->assertSee('Cold Brew');
});

test('a customer cannot access admin menu management', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.menu'))
        ->assertForbidden();
});

test('a staff user cannot access admin menu management', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.menu'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin menu management', function () {
    $this->get(route('admin.menu'))
        ->assertRedirect(route('login'));
});

test('an admin can search menu items by name', function () {
    $admin = User::factory()->admin()->create();
    MenuItem::factory()->create(['name' => 'Espresso Shot']);
    MenuItem::factory()->create(['name' => 'Veggie Wrap']);

    Volt::actingAs($admin)->test('admin.menu')
        ->set('search', 'Espresso')
        ->assertSee('Espresso Shot')
        ->assertDontSee('Veggie Wrap');
});

test('an admin can filter menu items by category', function () {
    $admin = User::factory()->admin()->create();
    $coffee = MenuCategory::factory()->create(['name' => 'Coffee']);
    $snacks = MenuCategory::factory()->create(['name' => 'Snacks']);
    MenuItem::factory()->create(['name' => 'Latte', 'menu_category_id' => $coffee->id]);
    MenuItem::factory()->create(['name' => 'Chips', 'menu_category_id' => $snacks->id]);

    Volt::actingAs($admin)->test('admin.menu')
        ->set('categoryFilter', $coffee->id)
        ->assertSee('Latte')
        ->assertDontSee('Chips');
});

test('an admin can filter menu items by availability', function () {
    $admin = User::factory()->admin()->create();
    MenuItem::factory()->create(['name' => 'In Stock Item']);
    MenuItem::factory()->unavailable()->create(['name' => 'Out Of Stock Item']);

    Volt::actingAs($admin)->test('admin.menu')
        ->set('availabilityFilter', 'available')
        ->assertSee('In Stock Item')
        ->assertDontSee('Out Of Stock Item');
});

test('the menu items list is paginated', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create();

    collect(range(1, 16))->each(function (int $i) use ($category) {
        MenuItem::factory()->create([
            'name' => "Item {$i}",
            'menu_category_id' => $category->id,
            'sort_order' => $i,
        ]);
    });

    $component = Volt::actingAs($admin)->test('admin.menu');

    $component->assertSee('Item 1')->assertDontSee('Item 16');
    $component->call('nextPage')->assertSee('Item 16');
});

test('an admin can create a menu item', function () {
    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create();

    Volt::actingAs($admin)->test('admin.menu')
        ->call('startCreate')
        ->set('menu_category_id', $category->id)
        ->set('name', 'Caramel Macchiato')
        ->set('slug', 'caramel-macchiato')
        ->set('base_price', '180.00')
        ->set('ingredientsInput', 'espresso, milk, caramel')
        ->call('save');

    $item = MenuItem::where('slug', 'caramel-macchiato')->first();
    expect($item)->not->toBeNull();
    expect($item->menu_category_id)->toBe($category->id);
    expect((float) $item->base_price)->toBe(180.00);
    expect($item->ingredients)->toBe(['espresso', 'milk', 'caramel']);
});

test('creating a menu item requires a category, name, slug and price', function () {
    $admin = User::factory()->admin()->create();

    Volt::actingAs($admin)->test('admin.menu')
        ->call('startCreate')
        ->set('name', '')
        ->set('slug', '')
        ->set('base_price', '')
        ->call('save')
        ->assertHasErrors(['menu_category_id', 'name', 'slug', 'base_price']);
});

test('an admin can edit a menu items price and availability', function () {
    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create(['base_price' => 100, 'is_available' => true]);

    Volt::actingAs($admin)->test('admin.menu')
        ->call('startEdit', $item->id)
        ->set('base_price', '125.50')
        ->set('is_available', false)
        ->call('save');

    $item->refresh();
    expect((float) $item->base_price)->toBe(125.50);
    expect($item->is_available)->toBeFalse();
});

test('an admin can toggle menu item availability from the list', function () {
    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create(['is_available' => true]);

    Volt::actingAs($admin)->test('admin.menu')
        ->call('toggleAvailable', $item->id);

    expect($item->refresh()->is_available)->toBeFalse();
});

test('editing a menu item without uploading a new photo keeps the existing image path', function () {
    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create(['image_path' => 'images/menu/existing.jpg']);

    Volt::actingAs($admin)->test('admin.menu')
        ->call('startEdit', $item->id)
        ->set('description', 'Updated description')
        ->call('save');

    expect($item->refresh()->image_path)->toBe('images/menu/existing.jpg');
});

test('uploading a new photo for a menu item stores it under the existing public images path', function () {
    File::ensureDirectoryExists(public_path('images/menu'));

    $admin = User::factory()->admin()->create();
    $category = MenuCategory::factory()->create();

    $component = Volt::actingAs($admin)->test('admin.menu')
        ->call('startCreate')
        ->set('menu_category_id', $category->id)
        ->set('name', 'Blueberry Muffin')
        ->set('slug', 'blueberry-muffin')
        ->set('base_price', '90.00')
        ->set('image', UploadedFile::fake()->create('muffin.jpg', 50, 'image/jpeg'))
        ->call('save');

    $item = MenuItem::where('slug', 'blueberry-muffin')->first();
    expect($item)->not->toBeNull();
    expect($item->image_path)->toStartWith('images/menu/blueberry-muffin-');
    expect(File::exists(public_path($item->image_path)))->toBeTrue();

    File::delete(public_path($item->image_path));
});

test('an admin can delete a menu item', function () {
    $admin = User::factory()->admin()->create();
    $item = MenuItem::factory()->create();

    Volt::actingAs($admin)->test('admin.menu')
        ->call('delete', $item->id);

    expect(MenuItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

test('deleting a menu item does not affect other customers active carts or past order history', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $item = MenuItem::factory()->create();

    $cart = Cart::factory()->for($customer)->create();
    CartItem::factory()->for($cart)->create(['menu_item_id' => $item->id]);

    $order = Order::factory()->for($customer)->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'menu_item_id' => $item->id,
        'item_name' => $item->name,
    ]);

    Volt::actingAs($admin)->test('admin.menu')
        ->call('delete', $item->id);

    expect(CartItem::where('menu_item_id', $item->id)->exists())->toBeTrue();
    expect(OrderItem::where('menu_item_id', $item->id)->exists())->toBeTrue();
});
