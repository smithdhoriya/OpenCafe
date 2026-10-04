@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'align' => 'left',
])

<div {{ $attributes->class([
    'flex flex-col gap-3',
    'items-center text-center' => $align === 'center',
]) }}>
    @if ($eyebrow)
        <flux:heading accent size="sm" class="uppercase tracking-wide">{{ $eyebrow }}</flux:heading>
    @endif

    <flux:heading size="xl">{{ $title }}</flux:heading>

    @if ($description)
        <p @class([
            'max-w-2xl text-espresso-600 dark:text-cream-300',
            'mx-auto' => $align === 'center',
        ])>{{ $description }}</p>
    @endif
</div>
