<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'menu_item_id',
        'menu_item_variant_id',
        'combo_id',
        'quantity',
        'special_instructions',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function isCombo(): bool
    {
        return $this->combo_id !== null;
    }

    /**
     * Price for a single unit: either the combo's bundle price, or the menu
     * item's base price plus the selected variant's price modifier, if any.
     */
    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->isCombo()
                ? (float) $this->combo->price
                : (float) $this->menuItem->base_price + (float) ($this->variant?->price_modifier ?? 0),
        );
    }

    protected function lineTotal(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->unit_price * $this->quantity,
        );
    }
}
