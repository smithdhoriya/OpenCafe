<?php

namespace App\Models;

use App\Enums\OfferType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Offer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_total',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_limit_per_user',
        'times_used',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => OfferType::class,
            'value' => 'decimal:2',
            'min_order_total' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Active right now: marked active, and within its optional validity
     * window if one is set.
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function meetsMinimumOrder(float $subtotal): bool
    {
        return $this->min_order_total === null || $subtotal >= (float) $this->min_order_total;
    }

    public function hasUsesRemaining(): bool
    {
        return $this->usage_limit === null || $this->times_used < $this->usage_limit;
    }

    /**
     * Whether the given user still has personal uses left, counted from
     * their own historical orders that actually used this offer.
     */
    public function hasUsesRemainingForUser(User $user): bool
    {
        if ($this->usage_limit_per_user === null) {
            return true;
        }

        $usedByUser = $this->orders()->where('user_id', $user->id)->count();

        return $usedByUser < $this->usage_limit_per_user;
    }

    /**
     * Discount for a given subtotal, server-computed and capped so it can
     * never exceed the subtotal (or the configured max, for percentage offers).
     */
    public function calculateDiscount(float $subtotal): float
    {
        $discount = match ($this->type) {
            OfferType::Percentage => $subtotal * ((float) $this->value / 100),
            OfferType::FixedAmount => (float) $this->value,
        };

        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(min($discount, $subtotal), 2);
    }
}
