<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = Volt::test('auth.reset-password', ['token' => $notification->token])
            ->set('email', $user->email)
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('resetPassword');

        $response
            ->assertHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

test('password cannot be reset with an invalid token', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    Volt::test('auth.reset-password', ['token' => 'this-token-does-not-exist'])
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('password cannot be reset with an expired token', function () {
    Notification::fake();

    $user = User::factory()->create();
    $originalPassword = $user->password;

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    $capturedToken = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$capturedToken) {
        $capturedToken = $notification->token;

        return true;
    });

    // config/auth.php: passwords.users.expire is 60 minutes.
    $this->travel(61)->minutes();

    Volt::test('auth.reset-password', ['token' => $capturedToken])
        ->set('email', $user->email)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('a used reset token cannot be reused', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::test('auth.forgot-password')
        ->set('email', $user->email)
        ->call('sendPasswordResetLink');

    $capturedToken = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$capturedToken) {
        $capturedToken = $notification->token;

        return true;
    });

    Volt::test('auth.reset-password', ['token' => $capturedToken])
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('resetPassword')
        ->assertHasNoErrors();

    $passwordAfterFirstReset = $user->fresh()->password;

    Volt::test('auth.reset-password', ['token' => $capturedToken])
        ->set('email', $user->email)
        ->set('password', 'another-password')
        ->set('password_confirmation', 'another-password')
        ->call('resetPassword')
        ->assertHasErrors('email');

    expect($user->fresh()->password)->toBe($passwordAfterFirstReset);
});
