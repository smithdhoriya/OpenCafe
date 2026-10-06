@props([
    'item',
    'eager' => false,
])

@if ($item->image_url)
    <img
        src="{{ $item->image_url }}"
        alt="{{ $item->name }}"
        width="1200"
        height="1200"
        loading="{{ $eager ? 'eager' : 'lazy' }}"
        @if ($eager) fetchpriority="high" @endif
        decoding="async"
        {{ $attributes->class('size-full object-cover') }}
    />
@else
    {{-- No photo set yet for this item — plain neutral fallback, not an illustration. --}}
    <div {{ $attributes->class('flex size-full items-center justify-center bg-linear-to-br from-orange-100 via-orange-50 to-espresso-200 px-3 text-center text-sm font-medium text-espresso-500 dark:from-espresso-700 dark:via-espresso-800 dark:to-espresso-900 dark:text-cream-300') }}>
        {{ $item->name }}
    </div>
@endif
