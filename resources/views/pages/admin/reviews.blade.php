<?php

use App\Enums\ReviewStatus;
use App\Models\Review;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, layout, state, title, updated, usesPagination};

usesPagination();

layout('components.layouts.admin');
title('Reviews — Admin — OpenCafe');

state([
    'statusFilter' => '',
    'showRejectForm' => false,
    'rejectingId' => null,
    'moderation_notes' => '',
]);

updated(['statusFilter' => fn () => $this->resetPage()]);

$reviews = computed(function () {
    return Review::query()
        ->with(['user', 'menuItem', 'order', 'moderator'])
        ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->paginate(15);
});

$approve = function (int $reviewId) {
    $review = Review::query()->whereKey($reviewId)->first();

    $review?->update([
        'status' => ReviewStatus::Approved,
        'is_visible' => true,
        'moderated_by' => Auth::id(),
        'moderated_at' => now(),
        'moderation_notes' => null,
    ]);

    Flux::toast(text: 'Review approved and is now visible to customers.', heading: 'Approved', variant: 'success');
};

$startReject = function (int $reviewId) {
    $this->rejectingId = $reviewId;
    $this->moderation_notes = '';
    $this->showRejectForm = true;
};

$confirmReject = function () {
    $validated = $this->validate([
        'moderation_notes' => ['nullable', 'string', 'max:1000'],
    ]);

    $review = Review::query()->whereKey($this->rejectingId)->first();

    $review?->update([
        'status' => ReviewStatus::Rejected,
        'is_visible' => false,
        'moderated_by' => Auth::id(),
        'moderated_at' => now(),
        'moderation_notes' => filled($validated['moderation_notes']) ? $validated['moderation_notes'] : null,
    ]);

    $this->showRejectForm = false;
    $this->rejectingId = null;
    $this->moderation_notes = '';

    Flux::toast(text: 'Review rejected and hidden from customers.', heading: 'Rejected', variant: 'success');
};

?>

<div>
    <x-admin.page-header heading="Reviews" subheading="Moderate customer feedback before it appears on the menu." />

    <div class="mb-4">
        <flux:select wire:model.live="statusFilter" class="max-w-xs">
            <flux:select.option value="">All statuses</flux:select.option>
            @foreach (ReviewStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ ucfirst($case->value) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->reviews->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-16 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
            No reviews match this filter.
        </div>
    @else
        <div class="flex flex-col gap-4">
            @foreach ($this->reviews as $review)
                <div wire:key="admin-review-{{ $review->id }}" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <x-customer.star-rating :rating="$review->rating" />
                                <flux:badge color="orange" size="sm">{{ ucfirst($review->status->value) }}</flux:badge>
                            </div>

                            <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $review->user?->name ?? $review->guest_name ?? 'Guest' }}
                                &middot; {{ $review->menuItem?->name ?? 'Menu item removed' }}
                                @if ($review->order)
                                    &middot; {{ $review->order->order_number }}
                                @endif
                            </span>
                        </div>

                        <div class="flex gap-2">
                            @if ($review->status !== ReviewStatus::Approved)
                                <flux:button wire:click="approve({{ $review->id }})" variant="primary" size="sm">Approve</flux:button>
                            @endif

                            @if ($review->status !== ReviewStatus::Rejected)
                                <flux:button wire:click="startReject({{ $review->id }})" variant="danger" size="sm">Reject</flux:button>
                            @endif
                        </div>
                    </div>

                    @if ($review->title)
                        <p class="mt-3 font-medium">{{ $review->title }}</p>
                    @endif

                    @if ($review->comment)
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $review->comment }}</p>
                    @endif

                    @if ($review->moderated_at)
                        <p class="mt-3 text-xs text-zinc-400 dark:text-zinc-500">
                            Moderated by {{ $review->moderator?->name ?? 'a since-removed admin' }} on {{ $review->moderated_at->format('d M Y, h:i A') }}
                            @if ($review->moderation_notes)
                                &middot; "{{ $review->moderation_notes }}"
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $this->reviews->links() }}
        </div>
    @endif

    <flux:modal wire:model.self="showRejectForm" class="w-full max-w-md">
        <form wire:submit="confirmReject" class="space-y-6">
            <flux:heading size="lg">Reject review</flux:heading>

            <flux:textarea wire:model="moderation_notes" label="Reason (optional, internal only)" rows="3" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showRejectForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="danger">Reject review</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
