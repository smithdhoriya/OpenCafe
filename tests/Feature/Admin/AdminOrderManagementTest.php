<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\CustomerAddress;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;
use Livewire\Volt\Volt;

test('an admin can view the orders list', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['order_number' => 'ORD-LIST-001']);

    $this->actingAs($admin)
        ->get(route('admin.orders'))
        ->assertOk()
        ->assertSee('ORD-LIST-001');
});

test('a customer cannot access the admin orders list', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.orders'))
        ->assertForbidden();
});

test('a staff user cannot access the admin orders list', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.orders'))
        ->assertForbidden();
});

test('a guest is redirected to login from the admin orders list', function () {
    $this->get(route('admin.orders'))
        ->assertRedirect(route('login'));
});

test('orders are listed newest first', function () {
    $admin = User::factory()->admin()->create();
    $older = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-OLDER',
        'placed_at' => now()->subDays(2),
    ]);
    $newer = Order::factory()->for(User::factory())->create([
        'order_number' => 'ORD-NEWER',
        'placed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.orders'))
        ->assertOk()
        ->assertSeeInOrder([$newer->order_number, $older->order_number]);
});

test('an admin can search orders by order number', function () {
    $admin = User::factory()->admin()->create();
    Order::factory()->for(User::factory())->create(['order_number' => 'ORD-FINDME-001']);
    Order::factory()->for(User::factory())->create(['order_number' => 'ORD-OTHER-002']);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->set('search', 'FINDME')
        ->assertSee('ORD-FINDME-001')
        ->assertDontSee('ORD-OTHER-002');
});

test('an admin can search orders by customer name', function () {
    $admin = User::factory()->admin()->create();
    $match = User::factory()->create(['name' => 'Jane Example']);
    $other = User::factory()->create(['name' => 'Someone Else']);
    Order::factory()->for($match)->create(['order_number' => 'ORD-BY-NAME']);
    Order::factory()->for($other)->create(['order_number' => 'ORD-NOT-BY-NAME']);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->set('search', 'Jane Example')
        ->assertSee('ORD-BY-NAME')
        ->assertDontSee('ORD-NOT-BY-NAME');
});

test('an admin can search orders by customer email', function () {
    $admin = User::factory()->admin()->create();
    $match = User::factory()->create(['email' => 'match.customer@example.com']);
    $other = User::factory()->create(['email' => 'other.customer@example.com']);
    Order::factory()->for($match)->create(['order_number' => 'ORD-BY-EMAIL']);
    Order::factory()->for($other)->create(['order_number' => 'ORD-NOT-BY-EMAIL']);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->set('search', 'match.customer@example.com')
        ->assertSee('ORD-BY-EMAIL')
        ->assertDontSee('ORD-NOT-BY-EMAIL');
});

test('an admin can filter orders by status', function () {
    $admin = User::factory()->admin()->create();
    Order::factory()->for(User::factory())->create(['order_number' => 'ORD-PENDING', 'status' => OrderStatus::Pending]);
    Order::factory()->for(User::factory())->create(['order_number' => 'ORD-COMPLETED', 'status' => OrderStatus::Completed]);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->set('status', OrderStatus::Completed->value)
        ->assertSee('ORD-COMPLETED')
        ->assertDontSee('ORD-PENDING');
});

test('an admin can filter orders by order type', function () {
    $admin = User::factory()->admin()->create();
    Order::factory()->for(User::factory())->create(['order_number' => 'ORD-TAKEAWAY', 'type' => OrderType::Takeaway]);
    Order::factory()->for(User::factory())->create(['order_number' => 'ORD-DELIVERY', 'type' => OrderType::Delivery]);

    Volt::actingAs($admin)->test('admin.orders.index')
        ->set('type', OrderType::Delivery->value)
        ->assertSee('ORD-DELIVERY')
        ->assertDontSee('ORD-TAKEAWAY');
});

test('the orders list is paginated', function () {
    $admin = User::factory()->admin()->create();

    collect(range(1, 16))->each(function (int $i) {
        Order::factory()->for(User::factory())->create([
            'order_number' => "ORD-PAGE-{$i}",
            'placed_at' => now()->subMinutes($i),
        ]);
    });

    $component = Volt::actingAs($admin)->test('admin.orders.index');

    // Newest (i = 1) is on page 1; the oldest (i = 16) only appears after paginating.
    $component->assertSee('ORD-PAGE-1')->assertDontSee('ORD-PAGE-16');

    $component->call('nextPage')->assertSee('ORD-PAGE-16');
});

test('an admin can view an order detail page', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->for(User::factory())->create(['order_number' => 'ORD-DETAIL-001']);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('ORD-DETAIL-001');
});

test('the order detail page shows the historical order item snapshot, not the current menu item', function () {
    $admin = User::factory()->admin()->create();
    $menuItem = MenuItem::factory()->create(['name' => 'Original Latte', 'base_price' => 150]);
    $order = Order::factory()->for(User::factory())->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'menu_item_id' => $menuItem->id,
        'item_name' => 'Original Latte',
        'unit_price' => 150,
        'quantity' => 2,
        'line_total' => 300,
    ]);

    // The menu item is renamed and repriced after the order was placed.
    $menuItem->update(['name' => 'Renamed Latte', 'base_price' => 999]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Original Latte')
        ->assertDontSee('Renamed Latte');
});

test('the order detail page shows the frozen delivery snapshot, not the live customer address', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $address = CustomerAddress::factory()->for($customer)->create([
        'address_line_1' => '1 Original Street',
        'city' => 'Original City',
    ]);
    $order = Order::factory()->for($customer)->delivery()->create();
    OrderDelivery::factory()->create([
        'order_id' => $order->id,
        'customer_address_id' => $address->id,
        'recipient_name' => 'Jane Doe',
        'address_line_1' => '1 Original Street',
        'city' => 'Original City',
        'distance_km' => 1.5,
        'delivery_charge' => 20,
    ]);

    // The saved address is edited after the order was placed.
    $address->update(['address_line_1' => '2 New Street', 'city' => 'New City']);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('1 Original Street')
        ->assertSee('Original City')
        ->assertDontSee('2 New Street')
        ->assertDontSee('New City');
});

test('an admin can view another customers order', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $order = Order::factory()->for($owner)->create(['order_number' => 'ORD-ANOTHER-CUSTOMER']);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('ORD-ANOTHER-CUSTOMER');
});

test('an invalid order id returns 404 on the admin order detail page', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/orders/'.($order->id + 9999))
        ->assertNotFound();
});

test('a customer cannot access the admin order detail page', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->for($customer)->create();

    $this->actingAs($customer)
        ->get(route('admin.orders.show', $order))
        ->assertForbidden();
});

test('customer-facing order routes remain unaffected by the admin orders section', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->for($customer)->create(['order_number' => 'ORD-CUSTOMER-FACING']);

    $this->actingAs($customer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('ORD-CUSTOMER-FACING');

    $this->actingAs($customer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('ORD-CUSTOMER-FACING');
});
