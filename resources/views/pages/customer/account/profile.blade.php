<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{layout, mount, state, title};

layout('components.layouts.customer');
title('My Profile — OpenCafe');

state(['name' => '', 'email' => '']);

mount(function () {
    $this->name = auth()->user()->name;
    $this->email = auth()->user()->email;
});

$save = function () {
    $user = auth()->user();

    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required', 'string', 'lowercase', 'email', 'max:255',
            Rule::unique(User::class)->ignore($user->id),
        ],
    ]);

    $user->fill($validated);

    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    $user->save();

    Flux::toast(text: 'Your profile has been updated.', heading: 'Saved', variant: 'success');
};

?>

<div class="mx-auto max-w-3xl px-6 py-20 lg:px-8">
    <div class="mb-8 flex flex-col gap-1">
        <flux:heading size="xl">My profile</flux:heading>
        <p class="text-espresso-600 dark:text-cream-300">Signed in as {{ auth()->user()->email }}.</p>
    </div>

    <div class="flex flex-col gap-6">
        <div class="rounded-2xl border border-espresso-100 bg-white p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm" class="mb-4">Account details</flux:heading>

            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:input wire:model="name" label="Name" required autocomplete="name" />
                <flux:input wire:model="email" label="Email" type="email" required autocomplete="email" />

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">Save changes</flux:button>
                </div>
            </form>
        </div>

        <div class="flex flex-col gap-2 rounded-2xl border border-espresso-100 bg-cream-100 p-5 dark:border-espresso-700 dark:bg-espresso-800/60">
            <flux:heading size="sm">Password</flux:heading>
            <p class="text-sm text-espresso-600 dark:text-cream-300">
                Need to change your password? That's handled on a dedicated, secure page.
            </p>
            <flux:button as="a" href="{{ route('settings.password') }}" wire:navigate variant="ghost" class="w-fit">
                Change password
            </flux:button>
        </div>
    </div>
</div>
