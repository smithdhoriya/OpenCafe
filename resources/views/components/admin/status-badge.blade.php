@props([
    'status',
])

@php
    use App\Enums\OrderStatus;

    $status = $status instanceof OrderStatus ? $status : OrderStatus::from($status);

    [$color, $icon] = match ($status) {
        OrderStatus::Pending => ['amber', 'clock'],
        OrderStatus::Confirmed => ['blue', 'check'],
        OrderStatus::Preparing => ['indigo', 'fire'],
        OrderStatus::Ready => ['cyan', 'check-badge'],
        OrderStatus::OutForDelivery => ['purple', 'truck'],
        OrderStatus::Completed => ['emerald', 'check-circle'],
        OrderStatus::Cancelled => ['red', 'x-circle'],
        OrderStatus::Refunded => ['zinc', 'arrow-path'],
    };

    $label = ucfirst(str_replace('_', ' ', $status->value));
@endphp

<flux:badge :color="$color" :icon="$icon" size="sm" {{ $attributes }}>
    {{ $label }}
</flux:badge>
