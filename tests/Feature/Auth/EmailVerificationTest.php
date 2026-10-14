<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an unsigned verification link is rejected', function () {
    $user = User::factory()->unverified()->create();

    $url = route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an expired verification link is rejected', function () {
    $user = User::factory()->unverified()->create();

    $expiredUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($expiredUrl)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verifying an already-verified email does not redispatch the Verified event', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    Event::fake();

    $this->actingAs($user)->get($verificationUrl);
    $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatchedTimes(Verified::class, 1);
});

test('registering a new account dispatches the email verification notification', function () {
    Notification::fake();

    Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('the resend-verification button sends a new verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    Volt::actingAs($user)->test('auth.verify-email')->call('sendVerification');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('resending verification does nothing once the email is already verified', function () {
    Notification::fake();

    $user = User::factory()->create();

    Volt::actingAs($user)->test('auth.verify-email')
        ->call('sendVerification')
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

test('resend-verification requests are throttled after repeated attempts', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $component = Volt::actingAs($user)->test('auth.verify-email');

    foreach (range(1, 6) as $_) {
        $component->call('sendVerification');
    }

    $component->call('sendVerification');

    Notification::assertSentTimes(VerifyEmail::class, 6);
});
