<?php

namespace App\Models;

use App\Enums\CartStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Cart extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'session_token',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * The user's existing active cart, if any, without creating one.
     */
    public static function activeForUser(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('status', CartStatus::Active)
            ->latest('id')
            ->first();
    }

    /**
     * The user's active cart, creating one if they don't have one yet.
     */
    public static function firstOrCreateForUser(User $user): self
    {
        return static::activeForUser($user) ?? static::create([
            'user_id' => $user->id,
            'status' => CartStatus::Active,
            'expires_at' => now()->addDay(),
        ]);
    }

    /**
     * Add a menu item to the cart, merging into an existing line (same menu
     * item + variant) by incrementing its quantity instead of duplicating it.
     *
     * Note: `cart_items.menu_item_variant_id` only stores a single variant,
     * so when a menu item has multiple variant groups only the one variant
     * passed in here is persisted — it does not capture every selection a
     * multi-group item's UI allows.
     */
    public function addItem(MenuItem $menuItem, ?MenuItemVariant $variant, int $quantity): CartItem
    {
        if (! $menuItem->is_available) {
            throw ValidationException::withMessages([
                'menu_item' => 'This item is currently unavailable.',
            ]);
        }

        if ($variant && (! $variant->is_available || $variant->group->menu_item_id !== $menuItem->id)) {
            throw ValidationException::withMessages([
                'variant' => 'That option is not available for this item.',
            ]);
        }

        $quantity = max(1, $quantity);

        $item = $this->items()
            ->where('menu_item_id', $menuItem->id)
            ->when(
                $variant,
                fn ($query) => $query->where('menu_item_variant_id', $variant->id),
                fn ($query) => $query->whereNull('menu_item_variant_id'),
            )
            ->first();

        if ($item) {
            $item->increment('quantity', $quantity);

            return $item;
        }

        return $this->items()->create([
            'menu_item_id' => $menuItem->id,
            'menu_item_variant_id' => $variant?->id,
            'quantity' => $quantity,
        ]);
    }

    /**
     * Add a combo to the cart, merging into an existing combo line by
     * incrementing its quantity instead of duplicating it.
     */
    public function addCombo(Combo $combo, int $quantity): CartItem
    {
        if (! $combo->isCurrentlyAvailable()) {
            throw ValidationException::withMessages([
                'combo' => 'This combo is currently unavailable.',
            ]);
        }

        $quantity = max(1, $quantity);

        $item = $this->items()->where('combo_id', $combo->id)->first();

        if ($item) {
            $item->increment('quantity', $quantity);

            return $item;
        }

        return $this->items()->create([
            'combo_id' => $combo->id,
            'quantity' => $quantity,
        ]);
    }
}
