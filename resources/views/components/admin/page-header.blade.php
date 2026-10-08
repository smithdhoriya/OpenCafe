@props([
    'heading',
    'subheading' => null,
])

<div class="mb-6">
    <flux:heading size="xl">{{ $heading }}</flux:heading>

    @if ($subheading)
        <flux:subheading>{{ $subheading }}</flux:subheading>
    @endif
</div>
