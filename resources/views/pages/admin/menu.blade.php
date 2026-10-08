<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Flux\Flux;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{computed, layout, state, title, updated, usesFileUploads, usesPagination};

usesPagination();
usesFileUploads();

layout('components.layouts.admin');
title('Menu — Admin — OpenCafe');

state([
    'search' => '',
    'categoryFilter' => '',
    'availabilityFilter' => '',

    'showForm' => false,
    'editingId' => null,
    'menu_category_id' => '',
    'name' => '',
    'slug' => '',
    'description' => '',
    'ingredientsInput' => '',
    'base_price' => '',
    'image' => null,
    'is_available' => true,
    'is_featured' => false,
    'preparation_time_minutes' => '',
    'sort_order' => 0,
    'currentImagePath' => null,
]);

updated([
    'search' => fn () => $this->resetPage(),
    'categoryFilter' => fn () => $this->resetPage(),
    'availabilityFilter' => fn () => $this->resetPage(),
]);

$categoryOptions = computed(fn () => MenuCategory::query()->orderBy('sort_order')->orderBy('name')->get());

$items = computed(function () {
    $term = '%'.trim($this->search).'%';

    return MenuItem::query()
        ->with('category')
        ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', $term))
        ->when($this->categoryFilter !== '', fn ($query) => $query->where('menu_category_id', $this->categoryFilter))
        ->when($this->availabilityFilter !== '', fn ($query) => $query->where('is_available', $this->availabilityFilter === 'available'))
        ->orderBy('menu_category_id')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->paginate(15);
});

$resetForm = function () {
    $this->editingId = null;
    $this->menu_category_id = '';
    $this->name = '';
    $this->slug = '';
    $this->description = '';
    $this->ingredientsInput = '';
    $this->base_price = '';
    $this->image = null;
    $this->is_available = true;
    $this->is_featured = false;
    $this->preparation_time_minutes = '';
    $this->sort_order = 0;
    $this->currentImagePath = null;
    $this->resetErrorBag();
};

$startCreate = function () {
    $this->resetForm();
    $this->showForm = true;
};

$startEdit = function (int $menuItemId) {
    $item = MenuItem::query()->whereKey($menuItemId)->first();

    if (! $item) {
        return;
    }

    $this->editingId = $item->id;
    $this->menu_category_id = $item->menu_category_id;
    $this->name = $item->name;
    $this->slug = $item->slug;
    $this->description = $item->description ?? '';
    $this->ingredientsInput = implode(', ', $item->ingredients ?? []);
    $this->base_price = (string) $item->base_price;
    $this->image = null;
    $this->is_available = $item->is_available;
    $this->is_featured = $item->is_featured;
    $this->preparation_time_minutes = $item->preparation_time_minutes !== null ? (string) $item->preparation_time_minutes : '';
    $this->sort_order = $item->sort_order;
    $this->currentImagePath = $item->image_path;

    $this->showForm = true;
};

$generateSlug = function () {
    $this->slug = Str::slug($this->name);
};

$save = function () {
    $validated = $this->validate([
        'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
        'name' => ['required', 'string', 'max:150'],
        'slug' => [
            'required', 'string', 'max:170', 'alpha_dash',
            Rule::unique('menu_items', 'slug')->ignore($this->editingId),
        ],
        'description' => ['nullable', 'string'],
        'ingredientsInput' => ['nullable', 'string'],
        'base_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        'is_available' => ['boolean'],
        'is_featured' => ['boolean'],
        'preparation_time_minutes' => ['nullable', 'integer', 'min:0', 'max:500'],
        'sort_order' => ['integer', 'min:0'],
    ]);

    $ingredients = collect(explode(',', $validated['ingredientsInput'] ?? ''))
        ->map(fn ($ingredient) => trim($ingredient))
        ->filter()
        ->values()
        ->all();

    $payload = [
        'menu_category_id' => $validated['menu_category_id'],
        'name' => $validated['name'],
        'slug' => $validated['slug'],
        'description' => filled($validated['description']) ? $validated['description'] : null,
        'ingredients' => $ingredients === [] ? null : $ingredients,
        'base_price' => $validated['base_price'],
        'is_available' => $validated['is_available'],
        'is_featured' => $validated['is_featured'],
        'preparation_time_minutes' => filled($validated['preparation_time_minutes']) ? $validated['preparation_time_minutes'] : null,
        'sort_order' => $validated['sort_order'],
    ];

    // Only replace the stored photo when a new one was actually uploaded —
    // otherwise the existing image_path is left untouched on edit.
    if ($this->image) {
        $filename = Str::slug($validated['name']).'-'.Str::random(6).'.'.$this->image->getClientOriginalExtension();
        File::ensureDirectoryExists(public_path('images/menu'));
        File::copy($this->image->getRealPath(), public_path('images/menu/'.$filename));
        $payload['image_path'] = 'images/menu/'.$filename;
    }

    if ($this->editingId) {
        $item = MenuItem::query()->whereKey($this->editingId)->first();

        if (! $item) {
            $this->showForm = false;
            $this->resetForm();

            return;
        }

        $item->update($payload);

        Flux::toast(text: 'Menu item updated.', heading: 'Saved', variant: 'success');
    } else {
        MenuItem::create($payload);

        Flux::toast(text: 'Menu item created.', heading: 'Saved', variant: 'success');
    }

    $this->showForm = false;
    $this->resetForm();
};

$toggleAvailable = function (int $menuItemId) {
    $item = MenuItem::query()->whereKey($menuItemId)->first();

    $item?->update(['is_available' => ! $item->is_available]);
};

$delete = function (int $menuItemId) {
    $item = MenuItem::query()->whereKey($menuItemId)->first();

    $item?->delete();

    Flux::toast(text: 'Menu item removed.', heading: 'Deleted', variant: 'success');
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <x-admin.page-header heading="Menu" subheading="All 45 menu items across every category." />

        <flux:button wire:click="startCreate" variant="primary" icon="plus">Add item</flux:button>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.400ms="search" placeholder="Search by name..." icon="magnifying-glass" />

        <flux:select wire:model.live="categoryFilter">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach ($this->categoryOptions as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="availabilityFilter">
            <flux:select.option value="">All availability</flux:select.option>
            <flux:select.option value="available">Available</flux:select.option>
            <flux:select.option value="unavailable">Unavailable</flux:select.option>
        </flux:select>
    </div>

    @if ($this->items->isEmpty())
        <div class="rounded-2xl border border-dashed border-zinc-200 bg-zinc-50 px-6 py-16 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
            No menu items match your filters.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-[760px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Item</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium text-right">Price</th>
                        <th class="px-4 py-3 font-medium">Availability</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900/40">
                    @foreach ($this->items as $item)
                        <tr wire:key="admin-menu-item-{{ $item->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 shrink-0 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                        <x-customer.menu-image :item="$item" class="size-full object-cover" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ $item->name }}</span>
                                        @if ($item->is_featured)
                                            <span class="text-xs text-orange-600 dark:text-orange-400">Featured</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $item->category?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><x-customer.price :amount="$item->base_price" /></td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$item->is_available ? 'lime' : 'zinc'" size="sm">
                                    {{ $item->is_available ? 'Available' : 'Unavailable' }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <flux:button wire:click="toggleAvailable({{ $item->id }})" variant="ghost" size="sm">
                                    {{ $item->is_available ? 'Mark unavailable' : 'Mark available' }}
                                </flux:button>
                                <flux:button wire:click="startEdit({{ $item->id }})" variant="ghost" size="sm" icon="pencil" square aria-label="Edit item" />
                                <flux:button
                                    wire:click="delete({{ $item->id }})"
                                    wire:confirm="Remove this menu item? This cannot be undone."
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    square
                                    aria-label="Delete item"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->items->links() }}
        </div>
    @endif

    <flux:modal wire:model.self="showForm" class="w-full max-w-2xl" @close="resetForm">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Edit menu item' : 'Add menu item' }}</flux:heading>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model="menu_category_id" label="Category" required class="sm:col-span-2">
                    <flux:select.option value="">Select a category...</flux:select.option>
                    @foreach ($this->categoryOptions as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" label="Name" required class="sm:col-span-2" />

                <div class="flex items-end gap-2 sm:col-span-2">
                    <flux:input wire:model="slug" label="Slug" required class="flex-1" />
                    <flux:button type="button" wire:click="generateSlug" variant="ghost">From name</flux:button>
                </div>

                <flux:textarea wire:model="description" label="Description (optional)" rows="3" class="sm:col-span-2" />
                <flux:textarea
                    wire:model="ingredientsInput"
                    label="Ingredients (optional, comma-separated)"
                    placeholder="e.g. cheese, lettuce, tomato"
                    rows="2"
                    class="sm:col-span-2"
                />

                <flux:input wire:model="base_price" label="Price" type="number" step="0.01" min="0" required />
                <flux:input wire:model="preparation_time_minutes" label="Prep time (minutes, optional)" type="number" min="0" />

                <div class="sm:col-span-2">
                    <flux:input wire:model="image" type="file" label="Photo (optional — leave empty to keep the current one)" accept="image/*" />

                    @if ($currentImagePath)
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Current file: {{ $currentImagePath }}</p>
                    @endif
                </div>

                <flux:input wire:model="sort_order" label="Sort order" type="number" min="0" />

                <div class="flex flex-col gap-2 sm:col-span-2">
                    <flux:checkbox wire:model="is_available" label="Available to order" />
                    <flux:checkbox wire:model="is_featured" label="Featured on the menu" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save item</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
