@php
    $adminNavLinks = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home'],
        ['label' => 'Orders', 'route' => 'admin.orders', 'icon' => 'shopping-bag'],
        ['label' => 'Menu', 'route' => 'admin.menu', 'icon' => 'book-open'],
        ['label' => 'Categories', 'route' => 'admin.categories', 'icon' => 'squares-2x2'],
        ['label' => 'Customers', 'route' => 'admin.customers', 'icon' => 'users'],
        ['label' => 'Offers & Combos', 'route' => 'admin.offers', 'icon' => 'tag'],
        ['label' => 'Reviews', 'route' => 'admin.reviews', 'icon' => 'star'],
        ['label' => 'Cafe Settings', 'route' => 'admin.settings', 'icon' => 'cog'],
        ['label' => 'AI Assistant', 'route' => 'admin.ai-assistant', 'icon' => 'sparkles'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <div class="mr-5 flex items-center gap-2">
                <a href="{{ route('admin.dashboard') }}" class="flex flex-1 items-center gap-2" wire:navigate>
                    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900">
                        <flux:icon.home variant="micro" />
                    </div>

                    <div class="grid flex-1 text-left text-sm">
                        <span class="truncate leading-none font-semibold">OpenCafe</span>
                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">Admin</span>
                    </div>
                </a>

                <x-admin.theme-toggle />
            </div>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Management" class="grid">
                    @foreach ($adminNavLinks as $link)
                        <flux:navlist.item
                            :icon="$link['icon']"
                            :href="route($link['route'])"
                            :current="request()->routeIs($link['route'])"
                            wire:navigate
                        >
                            {{ $link['label'] }}
                        </flux:navlist.item>
                    @endforeach
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-admin.theme-toggle />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
