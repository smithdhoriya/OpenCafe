<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        // Matches the throttle already applied to the signed verify link route
        // (throttle:6,1) — this is the user-initiated counterpart to that limit.
        $throttleKey = 'verification-notification:'.Auth::id();

        if (RateLimiter::tooManyAttempts($throttleKey, 6)) {
            Session::flash('status', 'verification-link-throttled');

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        title="Verify your email"
        description="Please verify your email address by clicking on the link we just emailed to you."
    />

    <x-auth-session-status
        class="text-center"
        :status="match (session('status')) {
            'verification-link-sent' => __('A new verification link has been sent to the email address you provided during registration.'),
            'verification-link-throttled' => __('Too many verification emails requested. Please wait a minute before trying again.'),
            default => null,
        }"
    />

    <div class="flex flex-col items-center justify-center gap-3">
        <flux:button wire:click="sendVerification" variant="primary" class="w-full">
            {{ __('Resend verification email') }}
        </flux:button>

        <button
            wire:click="logout"
            type="submit"
            class="rounded-md text-sm font-medium text-espresso-600 underline underline-offset-4 transition hover:text-accent-content focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none dark:text-cream-300"
        >
            {{ __('Log out') }}
        </button>
    </div>
</div>
