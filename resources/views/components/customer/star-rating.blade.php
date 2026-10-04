@props([
    'rating' => 0,
    'count' => null,
])

@php
    $rounded = (int) round($rating);
@endphp

<div {{ $attributes->class('flex items-center gap-1') }}>
    <div class="flex items-center gap-0.5 text-orange-500">
        @for ($star = 1; $star <= 5; $star++)
            <flux:icon.star variant="{{ $star <= $rounded ? 'solid' : 'outline' }}" class="size-4" />
        @endfor
    </div>

    @if ($count !== null)
        <span class="text-sm text-espresso-500 dark:text-cream-300">
            ({{ $count }})
        </span>
    @endif
</div>
