@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <h1 class="text-2xl font-bold tracking-tight text-espresso-900 dark:text-cream-50">{{ $title }}</h1>
    <p class="text-center text-sm text-espresso-600 dark:text-cream-300">{{ $description }}</p>
</div>
