<?php

namespace App\Models;

use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;

class CafeSetting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'is_public' => 'boolean',
        ];
    }

    /**
     * Read a setting's value by key, cast according to its stored type.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting === null || $setting->value === null) {
            return $default;
        }

        return match ($setting->type) {
            SettingType::Integer => (int) $setting->value,
            SettingType::Decimal => (float) $setting->value,
            SettingType::Boolean => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            SettingType::Json => json_decode($setting->value, true),
            SettingType::String => $setting->value,
        };
    }

    /**
     * Display symbol for the configured currency setting. Shared by any
     * code that formats money for display (AI assistant context, etc.)
     * so the symbol-per-currency-code mapping lives in exactly one place.
     */
    public static function currencySymbol(): string
    {
        $code = static::get('currency', 'INR');

        return match (strtoupper((string) $code)) {
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $code.' ',
        };
    }
}
