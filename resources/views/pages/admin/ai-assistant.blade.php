<?php

use App\Enums\UserRole;
use App\Services\Ai\AdminAssistant;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{layout, mount, state, title};

layout('components.layouts.admin');
title('Admin AI Assistant — Admin — OpenCafe');

state([
    'messages' => fn () => [
        ['from' => 'assistant', 'text' => "Hi! I'm the Admin AI. Ask me about today's sales, orders needing attention, best-selling items, offers, or request a full business overview."],
    ],
    'draft' => '',
    'locale' => 'en',
]);

// Defense in depth: the route is already behind ['auth', 'admin'], but a
// Livewire component can be re-invoked across the component's lifetime, so
// this re-checks the real, current role rather than trusting the route
// middleware ran once at initial page load.
mount(function () {
    abort_unless(auth()->user()?->role === UserRole::Admin, 403);
});

$setLocale = function (string $locale) {
    if (in_array($locale, ['en', 'hi', 'gu'], true)) {
        $this->locale = $locale;
    }
};

$send = function () {
    abort_unless(auth()->user()?->role === UserRole::Admin, 403);

    $text = trim($this->draft);

    if ($text === '') {
        return;
    }

    $this->draft = '';

    $maxLength = 800;

    if (mb_strlen($text) > $maxLength) {
        $this->messages[] = ['from' => 'admin', 'text' => $text];
        $this->messages[] = ['from' => 'assistant', 'text' => "Please keep your message under {$maxLength} characters so I can help you better."];

        return;
    }

    $rateLimitKey = 'admin-ai-assistant:'.auth()->id();

    if (RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
        $this->messages[] = ['from' => 'admin', 'text' => $text];
        $this->messages[] = ['from' => 'assistant', 'text' => "You're sending messages a little too fast — please wait a moment and try again."];

        return;
    }

    RateLimiter::hit($rateLimitKey, 60);

    $this->messages[] = ['from' => 'admin', 'text' => $text];

    try {
        $reply = app(AdminAssistant::class)->reply($this->messages, $this->locale, auth()->user());
    } catch (\Throwable $e) {
        report($e);
        $reply = "Sorry, I'm having trouble responding right now. Please try again in a moment.";
    }

    $this->messages[] = ['from' => 'assistant', 'text' => $reply];
};

$ask = function (string $text) {
    $this->draft = $text;
    $this->send();
};

?>

<div>
    <x-admin.page-header
        heading="Admin AI Assistant"
        subheading="Ask about sales, orders, menu performance, customers, and offers — read-only and grounded in your real data."
    />

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                <flux:icon.shield-check variant="micro" class="text-emerald-500" />
                Read-only — cannot change orders, prices, offers, or settings.
            </div>

            <div class="flex items-center overflow-hidden rounded-full border border-zinc-200 text-xs font-medium dark:border-zinc-700" role="group" aria-label="Reply language">
                @foreach (['en' => 'EN', 'hi' => 'हि', 'gu' => 'ગુ'] as $code => $label)
                    <button
                        type="button"
                        wire:click="setLocale('{{ $code }}')"
                        @class([
                            'px-3 py-1 transition',
                            'bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900' => $locale === $code,
                            'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' => $locale !== $code,
                        ])
                        aria-label="Reply in {{ $label }}"
                        aria-pressed="{{ $locale === $code ? 'true' : 'false' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex min-h-[28rem] flex-col rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/40">
            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6" style="max-height: 60vh;">
                @foreach ($messages as $message)
                    <div @class(['flex', 'justify-end' => $message['from'] === 'admin'])>
                        <div @class([
                            'max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm sm:max-w-[75%]',
                            'bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900' => $message['from'] === 'admin',
                            'bg-zinc-50 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100' => $message['from'] === 'assistant',
                        ])>
                            @if ($message['from'] === 'assistant')
                                <div class="[&_code]:rounded [&_code]:bg-zinc-200 [&_code]:px-1 [&_code]:py-0.5 [&_code]:dark:bg-zinc-700 [&_h1]:mt-3 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:first:mt-0 [&_h2]:mt-3 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:first:mt-0 [&_h3]:mt-2 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:first:mt-0 [&_li]:mt-0.5 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mt-2 [&_p]:first:mt-0 [&_strong]:font-semibold [&_table]:mt-3 [&_table]:w-full [&_table]:border-collapse [&_table]:text-xs [&_td]:border [&_td]:border-zinc-300 [&_td]:px-2 [&_td]:py-1 [&_td]:dark:border-zinc-600 [&_th]:border [&_th]:border-zinc-300 [&_th]:bg-zinc-100 [&_th]:px-2 [&_th]:py-1 [&_th]:dark:border-zinc-600 [&_th]:dark:bg-zinc-700 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">
                                    {!! \Illuminate\Support\Str::markdown($message['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>
                            @else
                                <p class="whitespace-pre-wrap">{{ $message['text'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div wire:loading wire:target="send, ask" class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                    <flux:icon.sparkles variant="micro" class="animate-pulse" />
                    Thinking…
                </div>
            </div>

            @if (count($messages) <= 1)
                <div class="flex flex-wrap gap-2 border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    @foreach ([
                        "Give me today's sales summary.",
                        'Which orders need attention?',
                        'Which menu items are selling best?',
                        'Show me low-performing menu items.',
                        'Summarize active offers.',
                        'Give me a business overview.',
                    ] as $suggestion)
                        <button
                            type="button"
                            wire:click="ask('{{ $suggestion }}')"
                            wire:loading.attr="disabled"
                            wire:target="send,ask"
                            class="rounded-xl border border-zinc-200 px-3 py-1.5 text-xs text-zinc-600 transition hover:border-zinc-400 hover:text-zinc-900 disabled:opacity-50 dark:border-zinc-600 dark:text-zinc-300 dark:hover:text-white"
                        >
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="flex items-center gap-2 border-t border-zinc-200 p-3 dark:border-zinc-700">
                <flux:input
                    wire:model="draft"
                    wire:loading.attr="disabled"
                    wire:target="send"
                    placeholder="Ask about sales, orders, menu performance…"
                    autocomplete="off"
                    class="flex-1"
                />
                <flux:button
                    type="submit"
                    icon="paper-airplane"
                    variant="primary"
                    square
                    aria-label="Send message"
                    wire:loading.attr="disabled"
                    wire:target="send"
                />
            </form>
        </div>
    </div>
</div>
