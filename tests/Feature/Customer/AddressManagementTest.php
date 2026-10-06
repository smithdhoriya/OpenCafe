<?php

use App\Models\CustomerAddress;
use App\Models\User;
use Livewire\Volt\Volt;

function validAddressFields(): array
{
    return [
        'recipient_name' => 'Jane Doe',
        'phone' => '+919999999999',
        'address_line_1' => '221B Baker Street',
        'city' => 'Bengaluru',
        'country' => 'India',
        'latitude' => '12.9716',
        'longitude' => '77.5946',
    ];
}

test('guests are redirected to login when visiting the addresses page', function () {
    $this->get(route('addresses.index'))->assertRedirect(route('login'));
});

test('an authenticated customer can view their addresses page', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('addresses.index'))
        ->assertOk()
        ->assertSee($address->address_line_1);
});

test('a customer can create a delivery address', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->set(validAddressFields())
        ->call('save');

    expect(CustomerAddress::count())->toBe(1);

    $address = CustomerAddress::first();
    expect($address->user_id)->toBe($user->id);
    expect($address->recipient_name)->toBe('Jane Doe');
});

test('the first address a customer creates automatically becomes their default', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->set(validAddressFields())
        ->call('save');

    expect(CustomerAddress::first()->is_default)->toBeTrue();
});

test('a second address does not become default automatically', function () {
    $user = User::factory()->create();
    CustomerAddress::factory()->for($user)->default()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->set(validAddressFields())
        ->call('save');

    expect(CustomerAddress::count())->toBe(2);
    expect(CustomerAddress::where('is_default', true)->count())->toBe(1);
});

test('creating an address requires the core fields', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->call('save')
        ->assertHasErrors([
            'recipient_name' => 'required',
            'phone' => 'required',
            'address_line_1' => 'required',
            'city' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

    expect(CustomerAddress::count())->toBe(0);
});

test('country is required and cannot be blanked out', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->set(validAddressFields())
        ->set('country', '')
        ->call('save')
        ->assertHasErrors(['country' => 'required']);

    expect(CustomerAddress::count())->toBe(0);
});

test('latitude and longitude must be valid geographic coordinates', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->set(validAddressFields())
        ->set('latitude', '999')
        ->set('longitude', 'not-a-number')
        ->call('save')
        ->assertHasErrors(['latitude', 'longitude']);

    expect(CustomerAddress::count())->toBe(0);
});

test('latitude and longitude accept valid boundary coordinates', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->set(validAddressFields())
        ->set('latitude', '-90')
        ->set('longitude', '180')
        ->call('save')
        ->assertHasNoErrors();

    expect(CustomerAddress::count())->toBe(1);
});

test('a customer can edit their own address', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->create(['city' => 'Old City']);

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->call('startEdit', $address->id)
        ->assertSet('editingId', $address->id)
        ->set('city', 'New City')
        ->call('save');

    expect($address->refresh()->city)->toBe('New City');
});

test('a customer cannot load another customers address into the edit form', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $address = CustomerAddress::factory()->for($owner)->create(['city' => 'Owner City']);

    Volt::actingAs($intruder)
        ->test('customer.addresses.index')
        ->call('startEdit', $address->id)
        ->assertSet('editingId', null)
        ->assertSet('showForm', false);

    expect($address->refresh()->city)->toBe('Owner City');
});

test('a customer cannot delete another customers address', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $address = CustomerAddress::factory()->for($owner)->create();

    Volt::actingAs($intruder)
        ->test('customer.addresses.index')
        ->call('delete', $address->id);

    expect(CustomerAddress::query()->whereKey($address->id)->exists())->toBeTrue();
});

test('a customer cannot set another customers address as default', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $address = CustomerAddress::factory()->for($owner)->create(['is_default' => false]);

    Volt::actingAs($intruder)
        ->test('customer.addresses.index')
        ->call('setDefault', $address->id);

    expect($address->refresh()->is_default)->toBeFalse();
});

test('a customer only sees their own addresses', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    CustomerAddress::factory()->for($user)->create(['address_line_1' => 'Mine Street']);
    CustomerAddress::factory()->for($other)->create(['address_line_1' => 'Theirs Street']);

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->assertSee('Mine Street')
        ->assertDontSee('Theirs Street');
});

test('setting a new default address unsets the previous default', function () {
    $user = User::factory()->create();
    $first = CustomerAddress::factory()->for($user)->default()->create();
    $second = CustomerAddress::factory()->for($user)->create(['is_default' => false]);

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->call('setDefault', $second->id);

    expect($first->refresh()->is_default)->toBeFalse();
    expect($second->refresh()->is_default)->toBeTrue();
    expect(CustomerAddress::where('user_id', $user->id)->where('is_default', true)->count())->toBe(1);
});

test('deleting the default address promotes another remaining address to default', function () {
    $user = User::factory()->create();
    $first = CustomerAddress::factory()->for($user)->default()->create();
    $second = CustomerAddress::factory()->for($user)->create(['is_default' => false]);

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->call('delete', $first->id);

    expect(CustomerAddress::query()->whereKey($first->id)->exists())->toBeFalse();
    expect($second->refresh()->is_default)->toBeTrue();
});

test('deleting the only address leaves no default behind', function () {
    $user = User::factory()->create();
    $address = CustomerAddress::factory()->for($user)->default()->create();

    Volt::actingAs($user)
        ->test('customer.addresses.index')
        ->call('delete', $address->id);

    expect(CustomerAddress::where('user_id', $user->id)->count())->toBe(0);
});
