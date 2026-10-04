@php
    $settings = \App\Models\CafeSetting::query()->pluck('value', 'key');
@endphp

<flux:footer class="border-t border-espresso-800 bg-espresso-900 text-cream-200">
    <div class="mx-auto grid w-full max-w-7xl gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4 lg:py-16">
        <div class="flex flex-col gap-4 sm:col-span-2 lg:col-span-1">
            <x-customer.brand inverted />
            <p class="max-w-xs text-sm text-cream-300">
                Honest coffee and food, roasted and made fresh every day — pulled straight from the neighborhood table.
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="sm" class="text-cream-50">Explore</flux:heading>
            <ul class="flex flex-col gap-2 text-sm text-cream-300">
                <li><a href="{{ route('menu.index') }}" wire:navigate class="transition hover:text-orange-400">Menu</a></li>
                <li><a href="{{ route('offers.index') }}" wire:navigate class="transition hover:text-orange-400">Offers &amp; Combos</a></li>
                <li><a href="{{ route('about') }}" wire:navigate class="transition hover:text-orange-400">About</a></li>
                <li><a href="{{ route('contact') }}" wire:navigate class="transition hover:text-orange-400">Contact</a></li>
            </ul>
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="sm" class="text-cream-50">Visit us</flux:heading>
            <ul class="flex flex-col gap-2.5 text-sm text-cream-300">
                <li class="flex items-start gap-2.5">
                    <flux:icon.map-pin variant="micro" class="mt-0.5 shrink-0 text-orange-400" />
                    {{ $settings['address'] ?? '123 Main Street, Springfield' }}
                </li>
                <li class="flex items-start gap-2.5">
                    <flux:icon.clock variant="micro" class="mt-0.5 shrink-0 text-orange-400" />
                    Mon–Sat 7am–6pm, Sun 8am–2pm
                </li>
            </ul>
        </div>

        <div class="flex flex-col gap-3">
            <flux:heading size="sm" class="text-cream-50">Get in touch</flux:heading>
            <ul class="flex flex-col gap-2.5 text-sm text-cream-300">
                <li class="flex items-center gap-2.5">
                    <flux:icon.phone variant="micro" class="shrink-0 text-orange-400" />
                    <a href="tel:{{ $settings['contact_phone'] ?? '' }}" class="transition hover:text-orange-400">
                        {{ $settings['contact_phone'] ?? '+1 555-0100' }}
                    </a>
                </li>
                <li class="flex items-center gap-2.5">
                    <flux:icon.envelope variant="micro" class="shrink-0 text-orange-400" />
                    <a href="mailto:{{ $settings['contact_email'] ?? '' }}" class="transition hover:text-orange-400">
                        {{ $settings['contact_email'] ?? 'hello@opencafe.test' }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-espresso-800">
        <p class="mx-auto max-w-7xl px-6 py-5 text-center text-xs text-cream-400 lg:px-8">
            &copy; {{ now()->year }} OpenCafe. All rights reserved.
        </p>
    </div>
</flux:footer>
