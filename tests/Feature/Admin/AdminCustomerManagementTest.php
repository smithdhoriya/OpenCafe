<?php

use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use Livewire\Volt\Volt;

test('an admin can view the customers list', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create(['name' => 'Priya Sharma']);

    $this->actingAs($admin)
        ->get(route('admin.customers'))
        ->assertOk()
        ->assertSee('Priya Sharma');
});

test('a customer cannot access admin customer management', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.customers'))
        ->assertForbidden();
});

test('a staff user cannot access admin customer management', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.customers'))
        ->assertForbidden();
});

test('a guest is redirected to login from admin customer management', function () {
    $this->get(route('admin.customers'))
        ->assertRedirect(route('login'));
});

test('the customers list only shows customer-role accounts', function () {
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create(['name' => 'Staff Member']);
    $customer = User::factory()->create(['name' => 'Real Customer']);

    $this->actingAs($admin)
        ->get(route('admin.customers'))
        ->assertOk()
        ->assertSee('Real Customer')
        ->assertDontSee('Staff Member');
});

test('an admin can search customers by name', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Alpha Customer']);
    User::factory()->create(['name' => 'Beta Customer']);

    Volt::actingAs($admin)->test('admin.customers.index')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Customer')
        ->assertDontSee('Beta Customer');
});

test('an admin can search customers by email', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Match Customer', 'email' => 'findme@example.com']);
    User::factory()->create(['name' => 'Other Customer', 'email' => 'other@example.com']);

    Volt::actingAs($admin)->test('admin.customers.index')
        ->set('search', 'findme@example.com')
        ->assertSee('Match Customer')
        ->assertDontSee('Other Customer');
});

test('the customers list is paginated', function () {
    $admin = User::factory()->admin()->create();

    collect(range(1, 16))->each(fn (int $i) => User::factory()->create(['name' => "Customer {$i}"]));

    $component = Volt::actingAs($admin)->test('admin.customers.index');

    $component->assertSee('Customer 1')->assertDontSee('Customer 16');
    $component->call('nextPage')->assertSee('Customer 16');
});

test('an admin can view a customer detail page with their orders and addresses', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create(['name' => 'Detail Customer', 'email' => 'detail@example.com']);
    CustomerAddress::factory()->for($customer)->create(['address_line_1' => '42 Market Street', 'city' => 'Surat']);
    Order::factory()->for($customer)->create(['order_number' => 'ORD-CUSTOMER-DETAIL']);

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee('Detail Customer')
        ->assertSee('detail@example.com')
        ->assertSee('42 Market Street')
        ->assertSee('ORD-CUSTOMER-DETAIL');
});

test('the customer detail page does not expose the password hash', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.customers.show', $customer));

    $response->assertOk();
    $response->assertDontSee($customer->password, escape: false);
});

test('an invalid customer id returns 404 on the admin customer detail page', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/customers/'.($customer->id + 9999))
        ->assertNotFound();
});

test('an admin cannot view an admin or staff account through the customer detail page', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.customers.show', $otherAdmin))
        ->assertNotFound();
});

test('a customer cannot access the admin customer detail page', function () {
    $customer = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.customers.show', $other))
        ->assertForbidden();
});
