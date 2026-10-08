<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
});

test('a customer account can authenticate with its email, role, and password intact', function () {
    $user = User::factory()->create([
        'email' => 'customer1@gmail.com',
        'role' => UserRole::Customer,
        'is_active' => true,
        'password' => Hash::make('Customer1@123'),
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('email', 'customer1@gmail.com')
        ->set('password', 'Customer1@123')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('a customer with no intended url is redirected to the OpenCafe customer home page', function () {
    $user = User::factory()->create();

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('home', absolute: false));
});

test('a customer redirected to login from a protected page returns to that page after login', function () {
    $user = User::factory()->create();

    // Hitting a protected customer page as a guest stores it as the
    // intended URL and bounces to login, exactly like a real browser visit.
    $this->get(route('checkout.index'))->assertRedirect(route('login'));

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('checkout.index', absolute: false));
});

test('an admin account can still authenticate after the customer login fix', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Admin1@123'),
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('email', $admin->email)
        ->set('password', 'Admin1@123')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin);
});

test('an admin redirected to login from a protected admin page still returns to that page after login', function () {
    $admin = User::factory()->admin()->create([
        'password' => Hash::make('Admin1@123'),
    ]);

    $this->get(route('admin.orders'))->assertRedirect(route('login'));

    LivewireVolt::test('auth.login')
        ->set('email', $admin->email)
        ->set('password', 'Admin1@123')
        ->call('login')
        ->assertRedirect(route('admin.orders', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
