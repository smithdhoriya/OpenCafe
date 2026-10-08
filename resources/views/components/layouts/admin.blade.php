@props([
    'heading' => null,
    'subheading' => null,
])

<x-layouts.admin.sidebar>
    <flux:main>
        @if ($heading)
            <div class="mb-6">
                <flux:heading size="xl">{{ $heading }}</flux:heading>

                @if ($subheading)
                    <flux:subheading>{{ $subheading }}</flux:subheading>
                @endif
            </div>
        @endif

        {{ $slot }}
    </flux:main>
</x-layouts.admin.sidebar>
