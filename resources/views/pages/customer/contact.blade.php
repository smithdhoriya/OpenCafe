<?php

use App\Models\CafeSetting;
use function Livewire\Volt\{layout, rules, state, title};

layout('components.layouts.customer');
title('Contact — OpenCafe');

state([
    'settings' => fn () => CafeSetting::query()->pluck('value', 'key'),
    'name' => '',
    'email' => '',
    'message' => '',
    'submitted' => false,
]);

rules([
    'name' => ['required', 'string', 'max:150'],
    'email' => ['required', 'email'],
    'message' => ['required', 'string', 'max:2000'],
]);

$send = function () {
    $this->validate();

    // UI shell only for now — no email delivery or persistence yet.
    $this->reset(['name', 'email', 'message']);
    $this->submitted = true;
};

?>

<div class="mx-auto max-w-5xl px-6 py-16 lg:px-8">
    <x-customer.section-heading
        eyebrow="Get in touch"
        title="Contact us"
        description="Questions, feedback, or catering requests — we'd love to hear from you."
        align="center"
        class="mx-auto mb-12 max-w-2xl"
    />

    <div class="grid gap-10 lg:grid-cols-5">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <flux:card class="flex flex-col gap-4 border-espresso-100 bg-white p-6 dark:border-espresso-700 dark:bg-espresso-800">
                <div class="flex items-start gap-3">
                    <flux:icon.map-pin variant="outline" class="mt-0.5 shrink-0 text-accent-content" />
                    <div>
                        <flux:heading size="sm">Address</flux:heading>
                        <p class="text-sm text-espresso-600 dark:text-cream-300">
                            {{ $settings['address'] ?? '123 Main Street, Springfield' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <flux:icon.phone variant="outline" class="mt-0.5 shrink-0 text-accent-content" />
                    <div>
                        <flux:heading size="sm">Phone</flux:heading>
                        <a href="tel:{{ $settings['contact_phone'] ?? '' }}" class="text-sm text-espresso-600 hover:text-accent-content dark:text-cream-300">
                            {{ $settings['contact_phone'] ?? '+1 555-0100' }}
                        </a>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <flux:icon.envelope variant="outline" class="mt-0.5 shrink-0 text-accent-content" />
                    <div>
                        <flux:heading size="sm">Email</flux:heading>
                        <a href="mailto:{{ $settings['contact_email'] ?? '' }}" class="text-sm text-espresso-600 hover:text-accent-content dark:text-cream-300">
                            {{ $settings['contact_email'] ?? 'hello@opencafe.test' }}
                        </a>
                    </div>
                </div>
            </flux:card>

            {{-- Reserved for a future embedded map --}}
            <div class="flex aspect-video items-center justify-center rounded-2xl bg-linear-to-br from-orange-100 to-espresso-200 text-espresso-500/60 dark:from-espresso-700 dark:to-espresso-900 dark:text-cream-100/30">
                <flux:icon.map variant="outline" class="size-12" />
            </div>
        </div>

        <div class="lg:col-span-3">
            <flux:card class="border-espresso-100 bg-white p-6 dark:border-espresso-700 dark:bg-espresso-800">
                @if ($submitted)
                    <div class="flex flex-col items-center gap-3 py-10 text-center">
                        <flux:icon.check-circle variant="outline" class="size-10 text-accent-content" />
                        <flux:heading size="lg">Message sent</flux:heading>
                        <p class="text-sm text-espresso-600 dark:text-cream-300">
                            Thanks for reaching out — we'll get back to you soon.
                        </p>
                        <flux:button variant="ghost" wire:click="$set('submitted', false)">
                            Send another message
                        </flux:button>
                    </div>
                @else
                    <form wire:submit="send" class="flex flex-col gap-4">
                        <flux:input wire:model="name" label="Name" placeholder="Your name" />
                        <flux:input wire:model="email" type="email" label="Email" placeholder="you@example.com" />
                        <flux:textarea wire:model="message" label="Message" placeholder="How can we help?" rows="5" />

                        <flux:button type="submit" variant="primary" class="self-start">
                            Send message
                        </flux:button>
                    </form>
                @endif
            </flux:card>
        </div>
    </div>
</div>
