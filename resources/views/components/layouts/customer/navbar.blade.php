@php
    $navLinks = [
        ['label' => 'Home', 'route' => 'home', 'pattern' => 'home'],
        ['label' => 'Menu', 'route' => 'menu.index', 'pattern' => 'menu.*'],
        ['label' => 'Offers', 'route' => 'offers.index', 'pattern' => 'offers.*'],
        ['label' => 'About', 'route' => 'about', 'pattern' => 'about'],
        ['label' => 'Contact', 'route' => 'contact', 'pattern' => 'contact'],
    ];
@endphp

<div x-data="{ open: false }" class="relative z-30">
    <flux:header container sticky class="border-b border-espresso-100 bg-cream-50/80 py-1 shadow-xs backdrop-blur-md dark:border-espresso-700/80 dark:bg-espresso-900/80">
        <a href="{{ route('home') }}" wire:navigate class="shrink-0">
            <x-customer.brand :tagline="false" />
        </a>

        <flux:navbar class="ml-8 max-lg:hidden">
            @foreach ($navLinks as $link)
                <flux:navbar.item :href="route($link['route'])" :current="request()->routeIs($link['pattern'])" wire:navigate>
                    {{ $link['label'] }}
                </flux:navbar.item>
            @endforeach
        </flux:navbar>

        <flux:spacer />

        <div class="flex items-center gap-1.5 sm:gap-2.5">
            <x-customer.theme-toggle />

            <livewire:customer.cart-indicator />

            <flux:separator vertical class="mx-1 h-6 max-sm:hidden" />

            @auth
                <flux:dropdown position="bottom" align="end" class="max-sm:hidden">
                    <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

                    <flux:menu>
                        <flux:menu.item href="{{ route('account.profile') }}" icon="user" wire:navigate>Profile</flux:menu.item>
                        <flux:menu.item href="{{ route('orders.index') }}" icon="shopping-bag" wire:navigate>Orders</flux:menu.item>
                        <flux:menu.item href="{{ route('addresses.index') }}" icon="map-pin" wire:navigate>Addresses</flux:menu.item>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                Log out
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <flux:button as="a" href="{{ route('login') }}" wire:navigate variant="ghost" class="max-sm:hidden">
                    Log in
                </flux:button>
                <flux:button as="a" href="{{ route('register') }}" wire:navigate variant="primary" class="max-sm:hidden">
                    Sign up
                </flux:button>
            @endauth

            <flux:button @click="open = !open" variant="ghost" square class="lg:hidden" aria-label="Toggle menu">
                <flux:icon x-show="!open" icon="bars-3" />
                <flux:icon x-show="open" x-cloak icon="x-mark" />
            </flux:button>
        </div>
    </flux:header>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="border-b border-espresso-100 bg-cream-50 shadow-lg lg:hidden dark:border-espresso-700 dark:bg-espresso-900"
    >
        <nav class="flex flex-col gap-1 px-6 py-4">
            @foreach ($navLinks as $link)
                <flux:navlist.item :href="route($link['route'])" :current="request()->routeIs($link['pattern'])" wire:navigate @click="open = false">
                    {{ $link['label'] }}
                </flux:navlist.item>
            @endforeach

            <flux:separator class="my-2" />

            @auth
                <div class="flex flex-col gap-2">
                    <p class="px-1 text-sm text-espresso-500 dark:text-cream-300">
                        Signed in as <span class="font-medium text-espresso-800 dark:text-cream-100">{{ auth()->user()->name }}</span>
                    </p>

                    <flux:button as="a" href="{{ route('account.profile') }}" wire:navigate variant="ghost" @click="open = false">
                        Profile
                    </flux:button>
                    <flux:button as="a" href="{{ route('orders.index') }}" wire:navigate variant="ghost" @click="open = false">
                        Orders
                    </flux:button>
                    <flux:button as="a" href="{{ route('addresses.index') }}" wire:navigate variant="ghost" @click="open = false">
                        Addresses
                    </flux:button>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:button type="submit" variant="primary" class="w-full">Log out</flux:button>
                    </form>
                </div>
            @else
                <div class="flex flex-col gap-2">
                    <flux:button as="a" href="{{ route('login') }}" wire:navigate variant="ghost" @click="open = false">
                        Log in
                    </flux:button>
                    <flux:button as="a" href="{{ route('register') }}" wire:navigate variant="primary" @click="open = false">
                        Sign up
                    </flux:button>
                </div>
            @endauth
        </nav>
    </div>
</div>
