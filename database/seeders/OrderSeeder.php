<?php

namespace Database\Seeders;

use App\Enums\OfferType;
use App\Enums\OrderType;
use App\Enums\UserRole;
use App\Models\CustomerAddress;
use App\Models\DeliveryRate;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    private const TAX_RATE = 0.08;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = MenuItem::with('variantGroups.variants')->get();
        $customers = User::query()->where('role', UserRole::Customer)->get();
        $offers = Offer::query()->where('is_active', true)->get();
        $deliveryRates = DeliveryRate::query()->orderBy('min_distance_km')->get();

        $orderTypes = [OrderType::DineIn, OrderType::Takeaway, OrderType::Delivery];

        $customers->random(min(15, $customers->count()))->values()->each(
            fn (User $customer, int $index) => $this->createOrder($menuItems, $offers, $deliveryRates, [
                'user' => $customer,
                'type' => $orderTypes[$index % count($orderTypes)],
                'state' => 'completed',
            ])
        );

        $customers->random(min(3, $customers->count()))->each(
            fn (User $customer) => $this->createOrder($menuItems, $offers, $deliveryRates, [
                'user' => $customer,
                'type' => OrderType::Takeaway,
                'state' => 'cancelled',
            ])
        );

        $customers->random(min(2, $customers->count()))->each(
            fn (User $customer) => $this->createOrder($menuItems, $offers, $deliveryRates, [
                'user' => $customer,
                'type' => OrderType::DineIn,
                'state' => 'pending',
            ])
        );

        collect(range(1, 2))->each(
            fn () => $this->createOrder($menuItems, $offers, $deliveryRates, [
                'user' => null,
                'type' => OrderType::Takeaway,
                'state' => 'completed',
                'guest' => true,
            ])
        );
    }

    /**
     * Build one order with coherent line items, totals, and (for delivery orders) a
     * matching order_deliveries row — keeping subtotal/discount/tax/total mutually
     * consistent instead of generating each table independently.
     *
     * @param  Collection<int, MenuItem>  $menuItems
     * @param  Collection<int, Offer>  $offers
     * @param  Collection<int, DeliveryRate>  $deliveryRates
     * @param  array{user: ?User, type: OrderType, state: string, guest?: bool}  $options
     */
    private function createOrder(Collection $menuItems, Collection $offers, Collection $deliveryRates, array $options): void
    {
        $lines = $menuItems->random(fake()->numberBetween(1, 3))->map(fn (MenuItem $menuItem) => [
            'menuItem' => $menuItem,
            'variant' => $menuItem->variantGroups->first()?->variants->first(),
            'quantity' => fake()->numberBetween(1, 2),
        ]);

        $subtotal = round($lines->sum(
            fn (array $line) => $this->unitPrice($line) * $line['quantity']
        ), 2);

        $offer = fake()->boolean(25)
            ? $offers->first(fn (Offer $offer) => $offer->min_order_total === null || $subtotal >= (float) $offer->min_order_total)
            : null;

        $discountTotal = $offer ? $this->discountFor($offer, $subtotal) : 0.0;
        $taxTotal = round(($subtotal - $discountTotal) * self::TAX_RATE, 2);

        $deliveryCharge = 0.0;
        $deliveryRate = null;
        $distanceKm = null;

        if ($options['type'] === OrderType::Delivery && $deliveryRates->isNotEmpty()) {
            $deliveryRate = $deliveryRates->random();
            $distanceKm = round(fake()->randomFloat(
                2,
                (float) $deliveryRate->min_distance_km,
                (float) ($deliveryRate->max_distance_km ?? $deliveryRate->min_distance_km + 5)
            ), 2);
            $deliveryCharge = (float) $deliveryRate->charge;
        }

        $total = round($subtotal - $discountTotal + $taxTotal + $deliveryCharge, 2);

        $factory = Order::factory()->{$options['state']}();

        if (! empty($options['guest'])) {
            $factory = $factory->guest();
        }

        $order = $factory->create([
            'user_id' => $options['user']?->id,
            'type' => $options['type'],
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'delivery_charge' => $deliveryCharge,
            'total' => $total,
            'offer_id' => $offer?->id,
            'offer_code' => $offer?->code,
        ]);

        $lines->each(function (array $line) use ($order) {
            $unitPrice = $this->unitPrice($line);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'menu_item_id' => $line['menuItem']->id,
                'menu_item_variant_id' => $line['variant']?->id,
                'item_name' => $line['menuItem']->name,
                'variant_name' => $line['variant']?->name,
                'unit_price' => $unitPrice,
                'quantity' => $line['quantity'],
                'line_total' => round($unitPrice * $line['quantity'], 2),
            ]);
        });

        if ($options['type'] === OrderType::Delivery) {
            $this->createDelivery($order, $options['user'], $deliveryRate, $distanceKm, $deliveryCharge);
        }
    }

    /**
     * @param  array{menuItem: MenuItem, variant: ?MenuItemVariant, quantity: int}  $line
     */
    private function unitPrice(array $line): float
    {
        return (float) $line['menuItem']->base_price + (float) ($line['variant']->price_modifier ?? 0);
    }

    private function discountFor(Offer $offer, float $subtotal): float
    {
        $discount = $offer->type === OfferType::Percentage
            ? $subtotal * ((float) $offer->value / 100)
            : (float) $offer->value;

        if ($offer->max_discount_amount !== null) {
            $discount = min($discount, (float) $offer->max_discount_amount);
        }

        return round(min($discount, $subtotal), 2);
    }

    private function createDelivery(
        Order $order,
        ?User $user,
        ?DeliveryRate $deliveryRate,
        ?float $distanceKm,
        float $deliveryCharge
    ): void {
        /** @var CustomerAddress|null $address */
        $address = $user?->customerAddresses()->first();

        OrderDelivery::factory()->create([
            'order_id' => $order->id,
            'customer_address_id' => $address?->id,
            'recipient_name' => $address->recipient_name ?? fake()->name(),
            'phone' => $address->phone ?? fake()->numerify('+1##########'),
            'address_line_1' => $address->address_line_1 ?? fake()->streetAddress(),
            'address_line_2' => $address->address_line_2 ?? null,
            'city' => $address->city ?? fake()->city(),
            'state' => $address->state ?? fake()->state(),
            'postal_code' => $address->postal_code ?? fake()->postcode(),
            'country' => $address->country ?? 'United States',
            'latitude' => $address->latitude ?? fake()->latitude(),
            'longitude' => $address->longitude ?? fake()->longitude(),
            'distance_km' => $distanceKm,
            'delivery_rate_id' => $deliveryRate?->id,
            'delivery_charge' => $deliveryCharge,
        ]);
    }
}
