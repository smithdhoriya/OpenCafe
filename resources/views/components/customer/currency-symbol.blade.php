@php
    $symbol = once(function () {
        $code = \App\Models\CafeSetting::query()->where('key', 'currency')->value('value') ?? 'INR';

        return match (strtoupper($code)) {
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $code.' ',
        };
    });
@endphp
{{ $symbol }}
