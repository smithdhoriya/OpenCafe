<?php

use App\Enums\UserRole;
use App\Models\CafeSetting;
use App\Services\Ai\AdminAnalyticsService;
use App\Services\Ai\AdminAssistant;
use Illuminate\Support\Facades\RateLimiter;
use function Livewire\Volt\{computed, layout, mount, state, title};

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

// Visual-only chart data for the business overview panel below — read-only,
// pre-aggregated by AdminAnalyticsService, and never fed into the AI prompt
// or Groq request. Purely a static complement to the chat.
$salesTrend = computed(function () {
    $analytics = app(AdminAnalyticsService::class);
    $window = $analytics->standardWindows()['last_30_days'];

    return $analytics->dailySalesTrend($window['from'], $window['to']);
});

$orderStatusChart = computed(function () {
    $breakdown = app(AdminAnalyticsService::class)->orderStatusBreakdown();

    return collect(['preparing', 'completed', 'cancelled', 'refunded'])
        ->mapWithKeys(fn (string $status) => [$status => $breakdown[$status] ?? 0]);
});

$topItemsChart = computed(function () {
    $analytics = app(AdminAnalyticsService::class);
    $window = $analytics->standardWindows()['last_30_days'];

    return $analytics->topSellingItems($window['from'], $window['to'], limit: 5);
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
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-100 bg-white p-4 dark:border-emerald-900/40 dark:bg-zinc-900">
            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                <flux:icon.shield-check variant="micro" class="text-emerald-600 dark:text-emerald-400" />
                Read-only — cannot change orders, prices, offers, or settings.
            </div>

            <div class="flex items-center overflow-hidden rounded-full border border-emerald-100 text-xs font-medium dark:border-emerald-900/40" role="group" aria-label="Reply language">
                @foreach (['en' => 'EN', 'hi' => 'हि', 'gu' => 'ગુ'] as $code => $label)
                    <button
                        type="button"
                        wire:click="setLocale('{{ $code }}')"
                        @class([
                            'px-3 py-1 transition',
                            'bg-emerald-600 text-white dark:bg-emerald-500' => $locale === $code,
                            'text-zinc-500 hover:bg-emerald-50 dark:text-zinc-400 dark:hover:bg-emerald-900/30' => $locale !== $code,
                        ])
                        aria-label="Reply in {{ $label }}"
                        aria-pressed="{{ $locale === $code ? 'true' : 'false' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Business overview: static, read-only charts complementing the chat below --}}
        <div class="rounded-2xl border border-emerald-100 bg-white p-4 dark:border-emerald-900/40 dark:bg-zinc-900 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">Business overview</flux:heading>
                <flux:subheading>Last 30 days</flux:subheading>
            </div>

            <div class="grid grid-cols-1 gap-6">
                {{-- Sales trend --}}
                @php
                    $trend = $this->salesTrend;
                    $totalRevenue = collect($trend)->sum('revenue');
                    $symbol = CafeSetting::currencySymbol();
                @endphp

                <div>
                    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-1">
                        <flux:subheading>Daily revenue (paid orders only)</flux:subheading>
                        <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                            {{ $symbol }}{{ number_format($totalRevenue, 2) }} total
                        </span>
                    </div>

                    @if ($totalRevenue <= 0)
                        <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-8 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                            No paid sales recorded in the last 30 days.
                        </div>
                    @else
                        @php
                            $chartWidth = 600;
                            $chartHeight = 140;
                            $maxRevenue = max(1.0, collect($trend)->max('revenue'));
                            $count = count($trend);
                            $stepX = $count > 1 ? $chartWidth / ($count - 1) : $chartWidth;

                            $points = collect($trend)->values()->map(function (array $day, int $i) use ($stepX, $chartHeight, $maxRevenue) {
                                return [
                                    'x' => round($i * $stepX, 2),
                                    'y' => round($chartHeight - 6 - (($day['revenue'] / $maxRevenue) * ($chartHeight - 16)), 2),
                                    'date' => $day['date'],
                                    'revenue' => $day['revenue'],
                                ];
                            });

                            $polylinePoints = $points->map(fn (array $p) => "{$p['x']},{$p['y']}")->implode(' ');
                            $areaPoints = "0,{$chartHeight} {$polylinePoints} {$chartWidth},{$chartHeight}";
                            $firstDay = \Illuminate\Support\Carbon::parse($trend[0]['date'])->format('j M');
                            $midDay = \Illuminate\Support\Carbon::parse($trend[intdiv($count, 2)]['date'])->format('j M');
                            $lastDay = \Illuminate\Support\Carbon::parse($trend[$count - 1]['date'])->format('j M');
                        @endphp

                        <svg
                            viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}"
                            preserveAspectRatio="none"
                            class="h-36 w-full"
                            role="img"
                            aria-label="Daily paid revenue from {{ $firstDay }} to {{ $lastDay }}, totaling {{ $symbol }}{{ number_format($totalRevenue, 2) }}"
                        >
                            <polygon points="{{ $areaPoints }}" fill="#10b981" fill-opacity="0.16" />
                            <polyline
                                points="{{ $polylinePoints }}"
                                fill="none"
                                stroke="#10b981"
                                stroke-width="2.5"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                            />
                        </svg>

                        <div class="mt-1 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ $firstDay }}</span>
                            <span>{{ $midDay }}</span>
                            <span>{{ $lastDay }}</span>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {{-- Order status breakdown --}}
                    <div>
                        <flux:subheading class="mb-3">Order status (all-time)</flux:subheading>

                        @php
                            $statusCounts = $this->orderStatusChart;
                            $maxStatusCount = max(1, $statusCounts->max());
                        @endphp

                        @if ($statusCounts->sum() <= 0)
                            <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-8 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                                No orders in these statuses yet.
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($statusCounts as $status => $count)
                                    <div wire:key="status-bar-{{ $status }}">
                                        <div class="mb-1 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-300">
                                            <span class="font-medium">{{ ucfirst($status) }}</span>
                                            <span>{{ number_format($count) }}</span>
                                        </div>
                                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <div
                                                class="h-full rounded-full bg-emerald-500"
                                                style="width: {{ $count > 0 ? max(4, round(($count / $maxStatusCount) * 100)) : 0 }}%"
                                            ></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Best-selling menu items --}}
                    <div>
                        <flux:subheading class="mb-3">Best-selling items (last 30 days)</flux:subheading>

                        @php $topItems = $this->topItemsChart; @endphp

                        @if (empty($topItems))
                            <div class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 px-4 py-8 text-center text-sm text-zinc-500 dark:border-emerald-900/40 dark:bg-emerald-950/10 dark:text-zinc-400">
                                No sales recorded in the last 30 days to rank menu items.
                            </div>
                        @else
                            @php $maxQuantity = max(1, collect($topItems)->max('quantity_sold')); @endphp

                            <div class="space-y-3">
                                @foreach ($topItems as $item)
                                    <div wire:key="top-item-bar-{{ $item['menu_item_id'] }}">
                                        <div class="mb-1 flex items-center justify-between text-xs text-zinc-600 dark:text-zinc-300">
                                            <span class="truncate pr-2 font-medium">{{ $item['name'] }}</span>
                                            <span class="shrink-0">{{ number_format($item['quantity_sold']) }} sold</span>
                                        </div>
                                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <div
                                                class="h-full rounded-full bg-emerald-500"
                                                style="width: {{ $item['quantity_sold'] > 0 ? max(4, round(($item['quantity_sold'] / $maxQuantity) * 100)) : 0 }}%"
                                            ></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="flex min-h-[28rem] flex-col rounded-2xl border border-emerald-100 bg-white dark:border-emerald-900/40 dark:bg-zinc-900">
            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6" style="max-height: 60vh;">
                @foreach ($messages as $message)
                    <div @class(['flex', 'justify-end' => $message['from'] === 'admin'])>
                        <div @class([
                            'max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm sm:max-w-[75%]',
                            'bg-emerald-700 text-white dark:bg-emerald-600' => $message['from'] === 'admin',
                            'bg-amber-50 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100' => $message['from'] === 'assistant',
                        ])>
                            @if ($message['from'] === 'assistant')
                                <div class="[&_code]:rounded [&_code]:bg-emerald-100 [&_code]:px-1 [&_code]:py-0.5 [&_code]:dark:bg-zinc-700 [&_h1]:mt-3 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:first:mt-0 [&_h2]:mt-3 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:first:mt-0 [&_h3]:mt-2 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:first:mt-0 [&_li]:mt-0.5 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mt-2 [&_p]:first:mt-0 [&_strong]:font-semibold [&_table]:mt-3 [&_table]:w-full [&_table]:border-collapse [&_table]:text-xs [&_td]:border [&_td]:border-emerald-200 [&_td]:px-2 [&_td]:py-1 [&_td]:dark:border-zinc-600 [&_th]:border [&_th]:border-emerald-200 [&_th]:bg-emerald-50 [&_th]:px-2 [&_th]:py-1 [&_th]:dark:border-zinc-600 [&_th]:dark:bg-zinc-700 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">
                                    {!! \Illuminate\Support\Str::markdown($message['text'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                                </div>
                            @else
                                <p class="whitespace-pre-wrap">{{ $message['text'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div wire:loading wire:target="send, ask" class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                    <flux:icon.sparkles variant="micro" class="animate-pulse text-emerald-500" />
                    Thinking…
                </div>
            </div>

            @if (count($messages) <= 1)
                <div class="flex flex-wrap gap-2 border-t border-emerald-100 px-4 py-3 dark:border-emerald-900/40">
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
                            class="rounded-xl border border-emerald-200 px-3 py-1.5 text-xs text-emerald-700 transition hover:border-emerald-400 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-900/30"
                        >
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            @endif

            <form wire:submit="send" class="flex items-center gap-2 border-t border-emerald-100 p-3 dark:border-emerald-900/40">
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
