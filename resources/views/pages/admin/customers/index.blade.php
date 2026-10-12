<?php

use App\Enums\UserRole;
use App\Models\User;
use function Livewire\Volt\{computed, layout, state, title, updated, usesPagination};

usesPagination();

layout('components.layouts.admin');
title('Customers — Admin — OpenCafe');

state(['search' => '']);

updated(['search' => fn () => $this->resetPage()]);

$customers = computed(function () {
    $term = '%'.trim($this->search).'%';

    return User::query()
        ->where('role', UserRole::Customer)
        ->withCount('orders')
        ->when(trim($this->search) !== '', function ($query) use ($term) {
            $query->where(function ($inner) use ($term) {
                $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        })
        ->orderByDesc('created_at')
        ->paginate(15);
});

?>

<div>
    <x-admin.page-header heading="Customers" subheading="Everyone with a customer account on OpenCafe." />

    <div class="mb-4">
        <flux:input
            wire:model.live.debounce.400ms="search"
            placeholder="Search by name or email..."
            icon="magnifying-glass"
            class="max-w-sm"
        />
    </div>

    @if ($this->customers->isEmpty())
        <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/40 px-6 py-16 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
            No customers match your search.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-emerald-100 dark:border-emerald-900/40">
            <table class="w-full min-w-[640px] divide-y divide-emerald-100 text-left text-sm dark:divide-emerald-900/40">
                <thead class="bg-emerald-50/60 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium text-right">Orders</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Joined</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-emerald-100 bg-white dark:divide-emerald-900/40 dark:bg-zinc-900">
                    @foreach ($this->customers as $customer)
                        <tr wire:key="admin-customer-{{ $customer->id }}" class="transition hover:bg-emerald-50/40 dark:hover:bg-emerald-900/10">
                            <td class="px-4 py-3 font-medium">{{ $customer->name }}</td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $customer->email }}</td>
                            <td class="px-4 py-3 text-right">{{ $customer->orders_count }}</td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$customer->is_active ? 'lime' : 'zinc'" size="sm">
                                    {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $customer->created_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <flux:button as="a" href="{{ route('admin.customers.show', $customer) }}" wire:navigate variant="ghost" size="sm">
                                    View
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->customers->links() }}
        </div>
    @endif
</div>
