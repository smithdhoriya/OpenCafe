<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="theme-customer min-h-screen bg-cream-50 text-espresso-900 antialiased dark:bg-espresso-900 dark:text-cream-100">
        <x-layouts.customer.navbar />

        <main>
            {{ $slot }}
        </main>

        <x-layouts.customer.footer />

        <livewire:customer.ai-assistant />

        <flux:toast />

        @fluxScripts
    </body>
</html>
