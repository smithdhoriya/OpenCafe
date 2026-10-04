<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="theme-customer flex min-h-svh flex-col items-center justify-center gap-8 bg-cream-50 p-6 antialiased md:p-10 dark:bg-espresso-900">
        <a href="{{ route('home') }}" wire:navigate>
            <x-customer.brand />
        </a>

        <div class="w-full max-w-md rounded-2xl border border-espresso-100 bg-white p-8 shadow-xl shadow-espresso-900/5 dark:border-espresso-700 dark:bg-espresso-800 dark:shadow-black/30">
            {{ $slot }}
        </div>

        @fluxScripts
    </body>
</html>
