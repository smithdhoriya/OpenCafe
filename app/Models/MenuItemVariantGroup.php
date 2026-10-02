<?php

namespace App\Models;

use App\Enums\VariantSelectionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItemVariantGroup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'menu_item_id',
        'name',
        'selection_type',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'selection_type' => VariantSelectionType::class,
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class);
    }
}
