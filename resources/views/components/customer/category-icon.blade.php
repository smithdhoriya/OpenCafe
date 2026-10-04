@props([
    'slug' => null,
])

@php
    $icon = match ($slug) {
        'burgers' => 'burger',
        'pizzas' => 'pizza',
        'frankie' => 'frankie',
        'coffee-beverages' => 'coffee',
        'sandwich' => 'sandwich',
        'fries' => 'fries',
        'vada-pav' => 'vada-pav',
        default => 'coffee',
    };
@endphp

<flux:icon :icon="$icon" variant="outline" {{ $attributes }} />
