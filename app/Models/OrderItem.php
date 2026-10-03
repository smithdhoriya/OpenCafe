<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'menu_item_variant_id',
        'combo_id',
        'item_name',
        'variant_name',
        'combo_components_snapshot',
        'unit_price',
        'quantity',
        'line_total',
        'special_instructions',
    ];

    protected function casts(): array
    {
        return [
            // Frozen combo composition at order time — independent of later combo_items edits.
            'combo_components_snapshot' => 'array',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
}
