@props([
    'amount',
    'compareAt' => null,
    'stacked' => false,
])

<span {{ $attributes->class($stacked ? 'flex flex-col items-end' : 'inline-flex items-baseline gap-2') }}>
    <span class="font-semibold"><x-customer.currency-symbol />{{ number_format((float) $amount, 2) }}</span>

    @if ($compareAt)
        <span class="text-sm text-espresso-400 line-through dark:text-cream-400">
            <x-customer.currency-symbol />{{ number_format((float) $compareAt, 2) }}
        </span>
    @endif
</span>
