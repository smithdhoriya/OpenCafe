<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'min_distance_km',
        'max_distance_km',
        'charge',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_distance_km' => 'decimal:2',
            'max_distance_km' => 'decimal:2',
            'charge' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function orderDeliveries(): HasMany
    {
        return $this->hasMany(OrderDelivery::class);
    }

    /**
     * Find the active rate tier covering the given distance, if any.
     *
     * A distance that falls on a tier boundary is resolved by the lowest
     * matching min_distance_km (e.g. 2km belongs to the 0-2km tier, not 2-5km).
     */
    public static function forDistance(float $km): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('min_distance_km', '<=', $km)
            ->where(function ($query) use ($km) {
                $query->whereNull('max_distance_km')
                    ->orWhere('max_distance_km', '>=', $km);
            })
            ->orderBy('min_distance_km')
            ->orderBy('sort_order')
            ->first();
    }
}
