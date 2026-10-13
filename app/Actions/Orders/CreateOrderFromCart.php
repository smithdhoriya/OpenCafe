<?php

namespace App\Actions\Orders;

use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\CafeSetting;
use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\DeliveryRate;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateOrderFromCart
{
    /**
     * Convert the authenticated user's active cart into a real Order, Order Items,
     * and (for delivery) an OrderDelivery row — recalculating everything server-side.
     *
     * @param  OrderType  $fulfillmentType  Already resolved/validated by the caller.
     * @param  int|null  $selectedAddressId  Only meaningful when $fulfillmentType is Delivery.
     * @param  string|null  $offerCode  Untrusted input: only a code, never a discount amount.
     */
    public function handle(User $user, OrderType $fulfillmentType, ?int $selectedAddressId, ?string $offerCode = null): Order
    {
        return DB::transaction(function () use ($user, $fulfillmentType, $selectedAddressId, $offerCode) {
            $cart = Cart::query()
                ->where('user_id', $user->id)
                ->where('status', CartStatus::Active)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            $items = $cart->items()
                ->with(['menuItem', 'variant', 'combo.comboItems.menuItem', 'combo.comboItems.variant'])
                ->orderBy('id')
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }

            foreach ($items as $item) {
                if ($item->isCombo()) {
                    if (! $item->combo || ! $item->combo->isCurrentlyAvailable()) {
                        throw ValidationException::withMessages([
                            'cart' => ($item->combo->name ?? 'A combo').' in your cart is no longer available.',
                        ]);
                    }

                    continue;
                }

                if (! $item->menuItem || ! $item->menuItem->is_available) {
                    throw ValidationException::withMessages([
                        'cart' => ($item->menuItem->name ?? 'An item').' in your cart is no longer available.',
                    ]);
                }

                if ($item->variant && ! $item->variant->is_available) {
                    throw ValidationException::withMessages([
                        'cart' => "The selected option for {$item->menuItem->name} is no longer available.",
                    ]);
                }
            }

            $subtotal = (float) $items->sum(fn ($item) => $item->line_total);

            [$discountTotal, $offer] = $offerCode !== null && trim($offerCode) !== ''
                ? $this->resolveOffer($user, $offerCode, $subtotal)
                : [0.0, null];

            [$deliveryCharge, $address, $distanceKm, $deliveryRate] = $fulfillmentType === OrderType::Delivery
                ? $this->resolveDelivery($user, $subtotal, $selectedAddressId)
                : [0.0, null, null, null];

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'type' => $fulfillmentType,
                'status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'currency' => CafeSetting::get('currency', 'INR'),
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => 0,
                'delivery_charge' => $deliveryCharge,
                'total' => $subtotal - $discountTotal + $deliveryCharge,
                'offer_id' => $offer?->id,
                'offer_code' => $offer?->code,
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                if ($item->isCombo()) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'combo_id' => $item->combo_id,
                        'item_name' => $item->combo->name,
                        // Frozen composition — independent of later combo/combo_items edits.
                        'combo_components_snapshot' => $item->combo->comboItems->map(fn ($comboItem) => [
                            'menu_item_name' => $comboItem->menuItem?->name,
                            'variant_name' => $comboItem->variant?->name,
                            'quantity' => $comboItem->quantity,
                        ])->all(),
                        'unit_price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'line_total' => $item->line_total,
                        'special_instructions' => $item->special_instructions,
                    ]);

                    continue;
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item->menu_item_id,
                    'menu_item_variant_id' => $item->menu_item_variant_id,
                    'item_name' => $item->menuItem->name,
                    'variant_name' => $item->variant?->name,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                    'special_instructions' => $item->special_instructions,
                ]);
            }

            if ($fulfillmentType === OrderType::Delivery) {
                OrderDelivery::create([
                    'order_id' => $order->id,
                    'customer_address_id' => $address->id,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                    'distance_km' => $distanceKm,
                    'delivery_rate_id' => $deliveryRate->id,
                    'delivery_charge' => $deliveryCharge,
                ]);
            }

            if ($offer) {
                $offer->increment('times_used');
            }

            $cart->update(['status' => CartStatus::Converted]);

            return $order;
        });
    }

    /**
     * Resolve and validate an offer code entirely from server-side state —
     * the caller only ever supplies the code string, never a discount value.
     *
     * @return array{0: float, 1: Offer}
     */
    private function resolveOffer(User $user, string $offerCode, float $subtotal): array
    {
        // Locked for the lifetime of this transaction (same as the cart above) so a
        // second, near-simultaneous checkout against the same code can't both read
        // "uses remaining" before either increments — without this, a capped offer
        // could be oversold by one or more uses under concurrent requests.
        $offer = Offer::query()->where('code', Str::upper(trim($offerCode)))->lockForUpdate()->first();

        if (! $offer || ! $offer->isCurrentlyActive()) {
            throw ValidationException::withMessages(['offer' => 'This offer code is not valid.']);
        }

        if (! $offer->meetsMinimumOrder($subtotal)) {
            throw ValidationException::withMessages(['offer' => 'Your order does not meet the minimum required for this offer.']);
        }

        if (! $offer->hasUsesRemaining() || ! $offer->hasUsesRemainingForUser($user)) {
            throw ValidationException::withMessages(['offer' => 'This offer has reached its usage limit.']);
        }

        return [$offer->calculateDiscount($subtotal), $offer];
    }

    /**
     * @return array{0: float, 1: CustomerAddress, 2: float, 3: DeliveryRate}
     */
    private function resolveDelivery(User $user, float $subtotal, ?int $selectedAddressId): array
    {
        if (! CafeSetting::get('delivery_enabled', false)) {
            throw ValidationException::withMessages(['delivery' => 'Delivery is currently unavailable.']);
        }

        if ($subtotal < (float) CafeSetting::get('min_order_for_delivery', 0)) {
            throw ValidationException::withMessages(['delivery' => 'Your order is below the minimum required for delivery.']);
        }

        // Scoped to the authenticated user: a tampered address id never resolves to
        // another customer's address.
        $address = $selectedAddressId
            ? $user->customerAddresses()->whereKey($selectedAddressId)->first()
            : null;

        if (! $address) {
            throw ValidationException::withMessages(['delivery' => 'Please select a valid delivery address.']);
        }

        $cafeLatitude = CafeSetting::get('cafe_latitude');
        $cafeLongitude = CafeSetting::get('cafe_longitude');

        if ($cafeLatitude === null || $cafeLongitude === null || $address->latitude === null || $address->longitude === null) {
            throw ValidationException::withMessages(['delivery' => 'Delivery is currently unavailable.']);
        }

        $distanceKm = Geo::distanceKm(
            (float) $address->latitude,
            (float) $address->longitude,
            (float) $cafeLatitude,
            (float) $cafeLongitude,
        );

        $deliveryRate = DeliveryRate::forDistance($distanceKm);

        if (! $deliveryRate) {
            throw ValidationException::withMessages(['delivery' => 'We cannot deliver to that address.']);
        }

        return [(float) $deliveryRate->charge, $address, $distanceKm, $deliveryRate];
    }

    /**
     * Build a collision-resistant order number using the configurable prefix,
     * verifying uniqueness against the database before it is used.
     */
    private function generateOrderNumber(): string
    {
        $prefix = CafeSetting::get('order_number_prefix', 'ORD');

        do {
            $candidate = sprintf('%s-%s-%s', $prefix, now()->format('Ymd'), Str::upper(Str::random(8)));
        } while (Order::query()->where('order_number', $candidate)->exists());

        return $candidate;
    }
}
