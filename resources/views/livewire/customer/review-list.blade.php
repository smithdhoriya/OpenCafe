<?php

use App\Enums\ReviewStatus;
use App\Models\Review;
use function Livewire\Volt\{computed, mount, state};

state(['menuItem' => null, 'limit' => 6]);

// Intentionally untyped: a typed `?MenuItem` default here gets eagerly
// container-resolved into a blank model instead of staying null, since
// Eloquent models are trivially constructible.
mount(function ($menuItem = null, int $limit = 6) {
    $this->menuItem = $menuItem;
    $this->limit = $limit;
});

$reviews = computed(function () {
    return Review::query()
        ->where('status', ReviewStatus::Approved)
        ->where('is_visible', true)
        ->when($this->menuItem, fn ($query) => $query->where('menu_item_id', $this->menuItem->id))
        ->with(['user', 'menuItem'])
        ->latest()
        ->limit($this->limit)
        ->get();
});

$averageRating = computed(function () {
    return round($this->reviews->avg('rating') ?? 0, 1);
});

?>

<div>
    @if ($this->reviews->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-3xl border border-dashed border-espresso-200 bg-cream-100 px-6 py-12 text-center dark:border-espresso-700 dark:bg-espresso-800/40">
            <flux:icon.star variant="outline" class="size-7 text-espresso-400 dark:text-cream-300" />
            <p class="text-sm text-espresso-500 dark:text-cream-300">No reviews yet.</p>
        </div>
    @else
        <div class="mb-8 flex items-center justify-center gap-3">
            <x-customer.star-rating :rating="$this->averageRating" />

            <span class="text-sm text-espresso-600 dark:text-cream-300">
                {{ $this->averageRating }} out of 5 ({{ $this->reviews->count() }} review{{ $this->reviews->count() === 1 ? '' : 's' }})
            </span>
        </div>

        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->reviews as $review)
                @php
                    $name = $review->user?->name ?? $review->guest_name ?? 'Guest';
                    $initials = collect(explode(' ', $name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
                @endphp

                <flux:card class="flex h-full flex-col gap-3 border-espresso-100 bg-white p-6 shadow-xs transition hover:-translate-y-1 hover:shadow-lg dark:border-espresso-700 dark:bg-espresso-800">
                    <span class="font-serif text-4xl leading-none text-orange-300 dark:text-orange-500/40" aria-hidden="true">&ldquo;</span>

                    <x-customer.star-rating :rating="$review->rating" class="-mt-2" />

                    @if ($review->title)
                        <flux:heading size="sm">{{ $review->title }}</flux:heading>
                    @endif

                    @if ($review->comment)
                        <p class="line-clamp-4 text-sm text-espresso-600 dark:text-cream-300">{{ $review->comment }}</p>
                    @endif

                    <div class="mt-auto flex items-center gap-3 border-t border-espresso-100 pt-4 dark:border-espresso-700">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-orange-100 text-sm font-semibold text-orange-700 dark:bg-espresso-700 dark:text-orange-400">
                            {{ strtoupper($initials) }}
                        </div>

                        <div class="flex flex-col leading-tight">
                            <span class="text-sm font-medium text-espresso-900 dark:text-cream-50">{{ $name }}</span>

                            @if (! $menuItem && $review->menuItem)
                                <span class="text-xs text-espresso-500 dark:text-cream-300">{{ $review->menuItem->name }}</span>
                            @endif
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
