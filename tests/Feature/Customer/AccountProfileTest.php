<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected to login when visiting the account profile page', function () {
    $this->get(route('account.profile'))->assertRedirect(route('login'));
});

test('an authenticated customer can view their own profile', function () {
    $user = User::factory()->create(['name' => 'Priya Sharma', 'email' => 'priya@example.com']);

    $this->actingAs($user)
        ->get(route('account.profile'))
        ->assertOk()
        ->assertSee('Priya Sharma')
        ->assertSee('priya@example.com');
});

test('a customer can update their own name and email', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    Volt::actingAs($user)->test('customer.account.profile')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('updating the profile without changing the email keeps verification status intact', function () {
    $user = User::factory()->create(['email' => 'same@example.com']);
    $verifiedAt = $user->email_verified_at;

    Volt::actingAs($user)->test('customer.account.profile')
        ->set('name', 'Updated Name')
        ->set('email', 'same@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    expect($user->email_verified_at->eq($verifiedAt))->toBeTrue();
});

test('profile updates require a name and a valid, unique email', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create(['email' => 'taken@example.com']);

    Volt::actingAs($user)->test('customer.account.profile')
        ->set('name', '')
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['name', 'email']);
});

test('updating a profile never changes another customers account', function () {
    $user = User::factory()->create(['name' => 'User One']);
    $otherUser = User::factory()->create(['name' => 'User Two', 'email' => 'two@example.com']);

    Volt::actingAs($user)->test('customer.account.profile')
        ->set('name', 'User One Updated')
        ->call('save');

    expect($user->refresh()->name)->toBe('User One Updated');
    expect($otherUser->refresh()->name)->toBe('User Two');
    expect($otherUser->refresh()->email)->toBe('two@example.com');
});

test('the customer navbar shows the authenticated customers actual name', function () {
    $user = User::factory()->create(['name' => 'Rohan Mehta']);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Rohan Mehta');
});

test('the customer navbar account menu links to profile, orders, and addresses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('account.profile'), escape: false)
        ->assertSee(route('orders.index'), escape: false)
        ->assertSee(route('addresses.index'), escape: false);
});

test('the account profile page only ever operates on the authenticated users own data', function () {
    $userA = User::factory()->create(['name' => 'Account A']);
    $userB = User::factory()->create(['name' => 'Account B']);

    $this->actingAs($userA)
        ->get(route('account.profile'))
        ->assertOk()
        ->assertSee('Account A')
        ->assertDontSee('Account B');
});
