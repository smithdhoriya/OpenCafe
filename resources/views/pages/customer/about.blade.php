<?php

use App\Models\CafeSetting;
use function Livewire\Volt\{computed, layout, state, title, with};

layout('components.layouts.customer');
title('About — OpenCafe');

state([
    'settings' => fn () => CafeSetting::query()->pluck('value', 'key'),
]);

$openingHours = computed(function () {
    return json_decode($this->settings['opening_hours'] ?? '[]', true) ?? [];
});

with([
    'values' => [
        ['icon' => 'sparkles', 'title' => 'Quality first', 'description' => 'Beans are roasted in small batches and food is made fresh to order — nothing sits under a heat lamp.'],
        ['icon' => 'heart', 'title' => 'Community at heart', 'description' => 'OpenCafe is a neighborhood table. Regulars get to know the team, and the team gets to know them back.'],
        ['icon' => 'globe-alt', 'title' => 'Responsibly sourced', 'description' => 'We work directly with growers and suppliers who share our standards for quality and fair pay.'],
    ],
]);

?>

<div>
    <section class="border-b border-espresso-100 bg-cream-100 dark:border-espresso-700 dark:bg-espresso-800/40">
        <div class="mx-auto max-w-4xl px-6 py-20 text-center lg:px-8">
            <flux:badge color="orange" size="lg" class="mb-4">Our story</flux:badge>

            <flux:heading size="2xl">{{ $settings['cafe_tagline'] ?? 'Great coffee, honestly made.' }}</flux:heading>

            <p class="mx-auto mt-4 max-w-2xl text-lg text-espresso-600 dark:text-cream-200">
                {{ $settings['cafe_name'] ?? 'OpenCafe' }} started as a single espresso machine and a simple idea:
                serve the kind of coffee and food we'd want to sit down for ourselves. Every cup and every plate
                still gets made that way today.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <x-customer.section-heading eyebrow="What we stand for" title="Our values" align="center" class="mx-auto mb-12 max-w-2xl" />

        <div class="grid gap-6 sm:grid-cols-3">
            @foreach ($values as $value)
                <flux:card class="flex flex-col items-center gap-3 border-espresso-100 bg-white p-6 text-center dark:border-espresso-700 dark:bg-espresso-800">
                    <div class="flex size-12 items-center justify-center rounded-full bg-orange-100 text-orange-700 dark:bg-espresso-700 dark:text-orange-400">
                        <flux:icon :icon="$value['icon']" variant="outline" class="size-6" />
                    </div>
                    <flux:heading size="lg">{{ $value['title'] }}</flux:heading>
                    <p class="text-sm text-espresso-600 dark:text-cream-300">{{ $value['description'] }}</p>
                </flux:card>
            @endforeach
        </div>
    </section>

    <section class="border-t border-espresso-100 bg-cream-100 dark:border-espresso-700 dark:bg-espresso-800/40">
        <div class="mx-auto grid max-w-5xl gap-10 px-6 py-16 sm:grid-cols-2 lg:px-8">
            <div class="flex flex-col gap-3">
                <flux:heading size="lg">Visit us</flux:heading>
                <p class="flex items-start gap-2 text-espresso-700 dark:text-cream-200">
                    <flux:icon.map-pin variant="outline" class="mt-0.5 shrink-0" />
                    {{ $settings['address'] ?? '123 Main Street, Springfield' }}
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <flux:heading size="lg">Opening hours</flux:heading>
                <ul class="flex flex-col gap-1 text-espresso-700 dark:text-cream-200">
                    @if ($this->openingHours)
                        <li class="flex items-center gap-2">
                            <flux:icon.clock variant="outline" class="shrink-0" />
                            Mon–Fri: {{ $this->openingHours['monday_friday'] ?? '—' }}
                        </li>
                        <li class="flex items-center gap-2">
                            <flux:icon.clock variant="outline" class="shrink-0" />
                            Saturday: {{ $this->openingHours['saturday'] ?? '—' }}
                        </li>
                        <li class="flex items-center gap-2">
                            <flux:icon.clock variant="outline" class="shrink-0" />
                            Sunday: {{ $this->openingHours['sunday'] ?? '—' }}
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 text-center lg:px-8">
        <flux:heading size="xl" class="mb-4">Come say hello</flux:heading>
        <p class="mx-auto mb-6 max-w-xl text-espresso-600 dark:text-cream-300">
            Stop by for a cup, or browse the menu and get your order ready before you arrive.
        </p>
        <flux:button as="a" href="{{ route('menu.index') }}" wire:navigate variant="primary">
            Browse the menu
        </flux:button>
    </section>
</div>
