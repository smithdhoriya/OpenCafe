<?php

use App\Models\MenuCategory;
use Flux\Flux;
use Illuminate\Support\Str;
use function Livewire\Volt\{computed, layout, state, title};

layout('components.layouts.admin');
title('Categories — Admin — OpenCafe');

state([
    'showForm' => false,
    'editingId' => null,
    'name' => '',
    'slug' => '',
    'description' => '',
    'image_path' => '',
    'is_active' => true,
    'sort_order' => 0,
]);

$categories = computed(function () {
    return MenuCategory::query()
        ->withCount('menuItems')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();
});

$resetForm = function () {
    $this->editingId = null;
    $this->name = '';
    $this->slug = '';
    $this->description = '';
    $this->image_path = '';
    $this->is_active = true;
    $this->sort_order = 0;
    $this->resetErrorBag();
};

$startCreate = function () {
    $this->resetForm();
    $this->showForm = true;
};

$startEdit = function (int $categoryId) {
    $category = MenuCategory::query()->whereKey($categoryId)->first();

    if (! $category) {
        return;
    }

    $this->editingId = $category->id;
    $this->name = $category->name;
    $this->slug = $category->slug;
    $this->description = $category->description ?? '';
    $this->image_path = $category->image_path ?? '';
    $this->is_active = $category->is_active;
    $this->sort_order = $category->sort_order;

    $this->showForm = true;
};

$generateSlug = function () {
    $this->slug = Str::slug($this->name);
};

$save = function () {
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:150'],
        'slug' => [
            'required', 'string', 'max:170', 'alpha_dash',
            'unique:menu_categories,slug'.($this->editingId ? ','.$this->editingId : ''),
        ],
        'description' => ['nullable', 'string'],
        'image_path' => ['nullable', 'string', 'max:255'],
        'is_active' => ['boolean'],
        'sort_order' => ['integer', 'min:0'],
    ]);

    $validated['description'] = filled($validated['description']) ? $validated['description'] : null;
    $validated['image_path'] = filled($validated['image_path']) ? $validated['image_path'] : null;

    if ($this->editingId) {
        $category = MenuCategory::query()->whereKey($this->editingId)->first();

        if (! $category) {
            $this->showForm = false;
            $this->resetForm();

            return;
        }

        $category->update($validated);

        Flux::toast(text: 'Category updated.', heading: 'Saved', variant: 'success');
    } else {
        MenuCategory::create($validated);

        Flux::toast(text: 'Category created.', heading: 'Saved', variant: 'success');
    }

    $this->showForm = false;
    $this->resetForm();
};

$toggleActive = function (int $categoryId) {
    $category = MenuCategory::query()->whereKey($categoryId)->first();

    $category?->update(['is_active' => ! $category->is_active]);
};

$delete = function (int $categoryId) {
    $category = MenuCategory::query()->withCount('menuItems')->whereKey($categoryId)->first();

    if (! $category) {
        return;
    }

    if ($category->menu_items_count > 0) {
        Flux::toast(
            text: 'This category still has menu items. Move or remove them first.',
            heading: 'Cannot delete category',
            variant: 'danger',
        );

        return;
    }

    $category->delete();

    Flux::toast(text: 'Category removed.', heading: 'Deleted', variant: 'success');
};

?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <x-admin.page-header heading="Categories" subheading="Menu categories shown to customers, in browse order." />

        <flux:button wire:click="startCreate" variant="primary" icon="plus">Add category</flux:button>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full min-w-[640px] divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-900/60 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Slug</th>
                    <th class="px-4 py-3 font-medium text-right">Items</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Order</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900/40">
                @foreach ($this->categories as $category)
                    <tr wire:key="admin-category-{{ $category->id }}">
                        <td class="px-4 py-3 font-medium">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $category->slug }}</td>
                        <td class="px-4 py-3 text-right">{{ $category->menu_items_count }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :color="$category->is_active ? 'lime' : 'zinc'" size="sm">
                                {{ $category->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">{{ $category->sort_order }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <flux:button wire:click="toggleActive({{ $category->id }})" variant="ghost" size="sm">
                                {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                            </flux:button>
                            <flux:button wire:click="startEdit({{ $category->id }})" variant="ghost" size="sm" icon="pencil" square aria-label="Edit category" />
                            <flux:button
                                wire:click="delete({{ $category->id }})"
                                wire:confirm="Delete this category? This cannot be undone."
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                square
                                aria-label="Delete category"
                            />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <flux:modal wire:model.self="showForm" class="w-full max-w-lg" @close="resetForm">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Edit category' : 'Add category' }}</flux:heading>

            <div class="grid grid-cols-1 gap-4">
                <flux:input wire:model="name" label="Name" required />

                <div class="flex items-end gap-2">
                    <flux:input wire:model="slug" label="Slug" required class="flex-1" />
                    <flux:button type="button" wire:click="generateSlug" variant="ghost">From name</flux:button>
                </div>

                <flux:textarea wire:model="description" label="Description (optional)" rows="3" />
                <flux:input wire:model="image_path" label="Image path (optional)" placeholder="images/categories/example.jpg" />
                <flux:input wire:model="sort_order" label="Sort order" type="number" min="0" />
                <flux:checkbox wire:model="is_active" label="Active (visible to customers)" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Save category</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
