<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItemVariant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'menu_item_variant_group_id',
        'name',
        'price_modifier',
        'is_available',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            // Signed: a smaller size or simpler option can legitimately reduce the base price.
            'price_modifier' => 'decimal:2',
            'is_available' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariantGroup::class, 'menu_item_variant_group_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'menu_item_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'menu_item_variant_id');
    }

    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'menu_item_variant_id');
    }
}
