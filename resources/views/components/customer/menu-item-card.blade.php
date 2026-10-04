@props([
    'item',
])

<flux:card {{ $attributes->class('group flex h-full flex-col gap-4 overflow-hidden border-espresso-100 bg-white p-0 shadow-xs transition duration-300 hover:-translate-y-1.5 hover:shadow-xl dark:border-espresso-700 dark:bg-espresso-800') }}>
    <div data-item-visual class="relative aspect-[4/3] overflow-hidden bg-linear-to-br from-orange-100 via-orange-50 to-espresso-200 dark:from-espresso-700 dark:via-espresso-800 dark:to-espresso-900">
        {{-- Placeholder visual treatment until real photography / 3D presentation is added --}}
        <div class="absolute inset-0 flex items-center justify-center transition duration-300 group-hover:scale-110">
            <x-customer.category-icon :slug="$item->category?->slug" class="size-14 text-espresso-400/50 dark:text-cream-100/25" />
        </div>

        @if ($item->is_featured)
            <flux:badge color="orange" class="absolute top-3 left-3 shadow-sm">Featured</flux:badge>
        @endif

        <div class="absolute right-3 bottom-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-espresso-900 shadow-sm backdrop-blur dark:bg-espresso-900/90 dark:text-cream-50">
            <x-customer.price :amount="$item->base_price" class="gap-0 text-xs" />
        </div>
    </div>

    <div class="flex flex-1 flex-col gap-1.5 px-5 pb-5">
        <flux:heading size="lg">{{ $item->name }}</flux:heading>

        @if ($item->description)
            <p class="line-clamp-2 text-sm text-espresso-600 dark:text-cream-300">{{ $item->description }}</p>
        @endif

        <flux:button as="a" href="{{ route('menu.show', $item) }}" wire:navigate variant="primary" class="mt-3 w-full">
            View item
        </flux:button>
    </div>
</flux:card>
