<?php

use App\Enums\ReviewStatus;
use App\Models\Combo;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Review;
use function Livewire\Volt\{layout, state, title};

layout('components.layouts.customer');
title('OpenCafe — Great coffee, honestly made.');

state([
    'categories' => fn () => MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
    'featuredItems' => fn () => MenuItem::query()->with('category')->where('is_available', true)->where('is_featured', true)->orderBy('sort_order')->limit(4)->get(),
    'heroMenuItem' => fn () => MenuItem::query()->where('slug', 'mexican-pizza')->first(),
    'offers' => fn () => Offer::query()->where('is_active', true)->limit(3)->get(),
    'combos' => fn () => Combo::query()->where('is_available', true)->orderBy('sort_order')->limit(3)->get(),
    'averageRating' => fn () => round(Review::query()->where('status', ReviewStatus::Approved)->where('is_visible', true)->avg('rating') ?? 0, 1),
    'reviewCount' => fn () => Review::query()->where('status', ReviewStatus::Approved)->where('is_visible', true)->count(),
]);

?>

<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-espresso-100 bg-linear-to-b from-cream-100 to-cream-50 dark:border-espresso-700 dark:from-espresso-800 dark:to-espresso-900">
        <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-orange-300/30 blur-3xl dark:bg-orange-500/10"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-24 size-96 rounded-full bg-espresso-300/20 blur-3xl dark:bg-espresso-600/10"></div>

        <div class="relative mx-auto grid max-w-7xl gap-12 px-6 py-24 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-32">
            <div class="flex flex-col items-start gap-7">
                <flux:badge color="orange" size="lg">Freshly roasted, daily</flux:badge>

                <h1 class="max-w-xl text-4xl font-bold tracking-tight text-espresso-900 sm:text-5xl lg:text-6xl dark:text-cream-50">
                    Great coffee,
                    <span class="text-accent-content">honestly made.</span>
                </h1>

                <p class="max-w-lg text-lg text-espresso-600 dark:text-cream-200">
                    OpenCafe pairs carefully sourced beans with a seasonal food menu — made to order, served with
                    care, every single day.
                </p>

                <div class="flex flex-wrap gap-3">
                    <flux:button as="a" href="{{ route('menu.index') }}" wire:navigate variant="primary" size="base" icon-trailing="arrow-right">
                        Browse the menu
                    </flux:button>
                    <flux:button as="a" href="{{ route('offers.index') }}" wire:navigate variant="ghost" size="base">
                        See today's offers
                    </flux:button>
                </div>

                @if ($reviewCount > 0)
                    <div class="flex items-center gap-3 text-sm text-espresso-600 dark:text-cream-300">
                        <x-customer.star-rating :rating="$averageRating" />
                        <span>{{ $averageRating }} rated by {{ $reviewCount }} customers</span>
                    </div>
                @endif
            </div>

            {{-- Scroll-controlled 3D tilt stage — see resources/js/scroll-tilt.js --}}
            <div data-hero-visual data-hero-stage class="relative mx-auto aspect-square w-full max-w-md">
                <div class="absolute inset-0 rounded-full bg-linear-to-br from-orange-200 via-orange-100 to-espresso-200 opacity-70 blur-2xl dark:from-espresso-700 dark:via-espresso-800 dark:to-espresso-900"></div>
                <div class="absolute inset-6 rounded-[2.5rem] border border-orange-900/10 dark:border-cream-50/10"></div>

                <div aria-hidden="true" data-tilt-shadow class="absolute inset-x-10 bottom-6 h-10 rounded-full bg-espresso-900/25 blur-xl dark:bg-black/50"></div>

                <div class="absolute inset-0 flex items-center justify-center overflow-hidden rounded-[2.5rem] bg-linear-to-br from-orange-100 to-cream-50 shadow-xl ring-1 ring-espresso-900/5 dark:from-espresso-800 dark:to-espresso-900 dark:ring-cream-50/5">
                    @if ($heroMenuItem)
                        <div data-tilt-target class="size-4/5">
                            <x-customer.menu-image :item="$heroMenuItem" eager class="size-full rounded-[1.75rem] object-cover" />
                        </div>
                    @else
                        <flux:icon.coffee variant="outline" class="size-20 text-orange-600/70 dark:text-orange-400/50" />
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8 lg:py-24">
            <x-customer.section-heading eyebrow="Explore" title="Shop by category" class="mb-12" />

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
                @foreach ($categories as $category)
                    <a
                        href="{{ route('menu.index', ['category' => $category->slug]) }}"
                        wire:navigate
                        class="group flex flex-col items-center gap-3 rounded-2xl border border-espresso-100 bg-white p-5 text-center shadow-xs transition hover:-translate-y-1 hover:border-orange-300 hover:shadow-lg dark:border-espresso-700 dark:bg-espresso-800 dark:hover:border-orange-500/40"
                    >
                        <div class="flex size-12 items-center justify-center rounded-full bg-orange-100 text-orange-700 transition group-hover:scale-110 dark:bg-espresso-700 dark:text-orange-400">
                            <x-customer.category-icon :slug="$category->slug" class="size-6" />
                        </div>
                        <span class="text-sm font-medium text-espresso-900 dark:text-cream-50">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured items --}}
    @if ($featuredItems->isNotEmpty())
        <section class="border-t border-espresso-100 bg-cream-100 dark:border-espresso-700 dark:bg-espresso-800/40">
            <div class="mx-auto max-w-7xl px-6 py-20 lg:px-8 lg:py-24">
                <x-customer.section-heading eyebrow="Fan favorites" title="Customer Favorites" class="mb-12" />

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($featuredItems as $item)
                        <x-customer.menu-item-card :item="$item" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Offers & combos teaser --}}
    @if ($offers->isNotEmpty() || $combos->isNotEmpty())
        <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8 lg:py-24">
            <x-customer.section-heading eyebrow="Save more" title="Offers & combos" class="mb-12" />

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($offers as $offer)
                    <div class="flex flex-col gap-2 rounded-2xl border border-dashed border-orange-300 bg-orange-50 p-6 dark:border-orange-600/40 dark:bg-espresso-800">
                        <flux:badge color="orange" class="w-fit">{{ $offer->code }}</flux:badge>
                        <flux:heading size="lg">{{ $offer->name }}</flux:heading>
                        <p class="text-sm text-espresso-600 dark:text-cream-300">{{ $offer->description }}</p>
                    </div>
                @endforeach

                @foreach ($combos as $combo)
                    <div class="flex flex-col gap-2 rounded-2xl border border-espresso-100 bg-white p-6 shadow-xs dark:border-espresso-700 dark:bg-espresso-800">
                        <flux:heading size="lg">{{ $combo->name }}</flux:heading>
                        <p class="text-sm text-espresso-600 dark:text-cream-300">{{ $combo->description }}</p>
                        <x-customer.price :amount="$combo->price" class="text-accent-content" />
                    </div>
                @endforeach
            </div>

            <div class="mt-10 text-center">
                <flux:button as="a" href="{{ route('offers.index') }}" wire:navigate variant="primary">
                    View all offers &amp; combos
                </flux:button>
            </div>
        </section>
    @endif

    {{-- Reviews --}}
    <section class="border-t border-espresso-100 bg-cream-100 dark:border-espresso-700 dark:bg-espresso-800/40">
        <div class="mx-auto max-w-7xl px-6 py-20 lg:px-8 lg:py-24">
            <x-customer.section-heading eyebrow="Customers love us" title="What people are saying" align="center" class="mx-auto mb-12 max-w-2xl" />

            <livewire:customer.review-list :limit="6" />
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="relative overflow-hidden border-t border-espresso-100 bg-espresso-900 dark:border-espresso-700">
        <div class="pointer-events-none absolute -top-16 left-1/2 size-80 -translate-x-1/2 rounded-full bg-orange-500/10 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-7xl flex-col items-center gap-4 px-6 py-20 text-center lg:px-8">
            <flux:heading size="xl" class="text-cream-50">Ready to order?</flux:heading>
            <p class="max-w-xl text-cream-200">Browse the full menu and build your order in minutes.</p>
            <flux:button as="a" href="{{ route('menu.index') }}" wire:navigate variant="primary" size="base">
                Order now
            </flux:button>
        </div>
    </section>
</div>
