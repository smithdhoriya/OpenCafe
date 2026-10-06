<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\CafeSetting;
use Illuminate\Database\Seeder;

class CafeSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'cafe_name',
                'value' => 'OpenCafe',
                'type' => SettingType::String,
                'group' => 'general',
                'label' => 'Cafe Name',
                'is_public' => true,
            ],
            [
                'key' => 'cafe_tagline',
                'value' => 'Great coffee, honestly made.',
                'type' => SettingType::String,
                'group' => 'general',
                'label' => 'Tagline',
                'is_public' => true,
            ],
            [
                'key' => 'contact_email',
                'value' => 'hello@opencafe.test',
                'type' => SettingType::String,
                'group' => 'general',
                'label' => 'Contact Email',
                'is_public' => true,
            ],
            [
                'key' => 'contact_phone',
                'value' => '+1 555-0100',
                'type' => SettingType::String,
                'group' => 'general',
                'label' => 'Contact Phone',
                'is_public' => true,
            ],
            [
                'key' => 'address',
                'value' => '123 Main Street, Springfield',
                'type' => SettingType::String,
                'group' => 'general',
                'label' => 'Address',
                'is_public' => true,
            ],
            [
                'key' => 'currency',
                'value' => 'INR',
                'type' => SettingType::String,
                'group' => 'general',
                'label' => 'Currency',
                'is_public' => true,
            ],
            [
                'key' => 'opening_hours',
                'value' => json_encode([
                    'monday_friday' => '07:00-18:00',
                    'saturday' => '08:00-18:00',
                    'sunday' => '08:00-14:00',
                ]),
                'type' => SettingType::Json,
                'group' => 'general',
                'label' => 'Opening Hours',
                'is_public' => true,
            ],
            [
                'key' => 'tax_rate_percent',
                'value' => '8.5',
                'type' => SettingType::Decimal,
                'group' => 'pricing',
                'label' => 'Tax Rate (%)',
                'is_public' => false,
            ],
            [
                'key' => 'delivery_enabled',
                'value' => '1',
                'type' => SettingType::Boolean,
                'group' => 'delivery',
                'label' => 'Delivery Enabled',
                'is_public' => true,
            ],
            [
                'key' => 'min_order_for_delivery',
                'value' => '100.00',
                'type' => SettingType::Decimal,
                'group' => 'delivery',
                'label' => 'Minimum Order for Delivery',
                'is_public' => true,
            ],
            [
                'key' => 'cafe_latitude',
                'value' => '12.9716000',
                'type' => SettingType::Decimal,
                'group' => 'delivery',
                'label' => 'Cafe Latitude',
                'is_public' => false,
            ],
            [
                'key' => 'cafe_longitude',
                'value' => '77.5946000',
                'type' => SettingType::Decimal,
                'group' => 'delivery',
                'label' => 'Cafe Longitude',
                'is_public' => false,
            ],
            [
                'key' => 'dine_in_enabled',
                'value' => '1',
                'type' => SettingType::Boolean,
                'group' => 'ordering',
                'label' => 'Dine-in Enabled',
                'is_public' => true,
            ],
            [
                'key' => 'takeaway_enabled',
                'value' => '1',
                'type' => SettingType::Boolean,
                'group' => 'ordering',
                'label' => 'Takeaway Enabled',
                'is_public' => true,
            ],
            [
                'key' => 'order_number_prefix',
                'value' => 'ORD',
                'type' => SettingType::String,
                'group' => 'ordering',
                'label' => 'Order Number Prefix',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $setting) {
            CafeSetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
