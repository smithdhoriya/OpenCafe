<?php

use function Livewire\Volt\{state};

state([
    'open' => false,
    'draft' => '',
    'messages' => fn () => [
        ['from' => 'assistant', 'text' => "Hi! I'm the OpenCafe assistant. Ask me about the menu, hours, or today's offers."],
    ],
]);

$toggle = function () {
    $this->open = ! $this->open;
};

$send = function () {
    $text = trim($this->draft);

    if ($text === '') {
        return;
    }

    $this->messages[] = ['from' => 'customer', 'text' => $text];
    $this->messages[] = ['from' => 'assistant', 'text' => 'Thanks for reaching out! This assistant is just a preview for now — a real one is on the way.'];
    $this->draft = '';
};

$ask = function (string $text) {
    $this->draft = $text;
    $this->send();
};

?>

<div class="fixed right-6 bottom-6 z-40 flex flex-col items-end gap-3">
    @if ($open)
        <flux:card class="flex h-[26rem] w-80 max-w-[calc(100vw-3rem)] flex-col gap-0 overflow-hidden border-espresso-100 bg-white p-0 shadow-2xl dark:border-espresso-700 dark:bg-espresso-800">
            <div class="flex items-center justify-between gap-2 bg-linear-to-r from-espresso-900 to-espresso-800 px-4 py-3.5 dark:from-espresso-950 dark:to-espresso-900">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-8 items-center justify-center rounded-full bg-orange-500/20 text-orange-400">
                        <flux:icon.sparkles variant="solid" class="size-4" />
                    </div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-cream-50">OpenCafe Assistant</p>
                        <p class="text-xs text-cream-300">Preview — not live yet</p>
                    </div>
                </div>

                <button type="button" wire:click="toggle" class="text-cream-300 transition hover:text-cream-50" aria-label="Close assistant">
                    <flux:icon.x-mark variant="micro" />
                </button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto bg-cream-50 px-4 py-4 dark:bg-espresso-900/40">
                @foreach ($messages as $message)
                    <div @class([
                        'max-w-[85%] rounded-2xl px-3.5 py-2 text-sm shadow-sm',
                        'ml-auto bg-(--color-accent) text-(--color-accent-foreground)' => $message['from'] === 'customer',
                        'bg-white text-espresso-800 dark:bg-espresso-700 dark:text-cream-100' => $message['from'] === 'assistant',
                    ])>
                        {{ $message['text'] }}
                    </div>
                @endforeach
            </div>

            @if (count($messages) <= 1)
                <div class="flex flex-wrap gap-1.5 border-t border-espresso-100 bg-white px-3 py-2.5 dark:border-espresso-700 dark:bg-espresso-800">
                    @foreach (['Today\'s hours?', 'Popular items?', 'Any offers?'] as $suggestion)
                        <button
                            type="button"
                            wire:click="ask('{{ $suggestion }}')"
                            class="rounded-xl border border-espresso-200 px-3 py-1 text-xs text-espresso-600 transition hover:border-orange-300 hover:text-accent-content dark:border-espresso-600 dark:text-cream-300"
                        >
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="flex items-center gap-2 border-t border-espresso-100 p-3 dark:border-espresso-700">
                <flux:input wire:model="draft" placeholder="Ask something…" class="flex-1" />
                <flux:button type="submit" icon="paper-airplane" variant="primary" square aria-label="Send message" />
            </form>
        </flux:card>
    @endif

    <div class="relative">
        @unless ($open)
            <span class="absolute inset-0 animate-ping rounded-full bg-orange-400/50"></span>
        @endunless

        <flux:button
            wire:click="toggle"
            variant="primary"
            square
            class="relative size-14 rounded-full shadow-lg"
            aria-label="{{ $open ? 'Close assistant' : 'Open assistant' }}"
        >
            <flux:icon :icon="$open ? 'x-mark' : 'sparkles'" variant="solid" class="size-6" />
        </flux:button>
    </div>
</div>
