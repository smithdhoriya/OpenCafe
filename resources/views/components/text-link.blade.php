<a
    {{ $attributes->merge(['class' => 'text-sm font-medium text-accent-content underline-offset-4 transition hover:underline focus-visible:underline focus-visible:outline-none']) }}
    wire:navigate
>
    {{ $slot }}
</a>
