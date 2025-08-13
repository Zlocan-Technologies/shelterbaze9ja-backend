<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            [
                'key' => 'engagement_fee',
                'value' => '5000',
                'description' => 'Fee to see property contact details (in kobo)',
                'type' => 'number',
                'is_public' => true
            ],
            [
                'key' => 'shelterbaze_commission',
                'value' => '10.0',
                'description' => 'Commission percentage on rent',
                'type' => 'number',
                'is_public' => false
            ],
            [
                'key' => 'savings_deposit_charge',
                'value' => '2.0',
                'description' => 'Charge percentage on savings deposits',
                'type' => 'number',
                'is_public' => true
            ],
            [
                'key' => 'early_withdrawal_penalty',
                'value' => '5.0',
                'description' => 'Penalty percentage for early withdrawal',
                'type' => 'number',
                'is_public' => true
            ],
            [
                'key' => 'shelterbaze_bank_details',
                'value' => json_encode([
                    'account_number' => '1234567890',
                    'bank_name' => 'First Bank of Nigeria',
                    'account_name' => 'Shelterbaze Limited'
                ]),
                'description' => 'Bank details for rent payments',
                'type' => 'json',
                'is_public' => true
            ],
            [
                'key' => 'app_maintenance_mode',
                'value' => 'false',
                'description' => 'Enable/disable app maintenance mode',
                'type' => 'boolean',
                'is_public' => true
            ],
            [
                'key' => 'max_property_images',
                'value' => '10',
                'description' => 'Maximum number of images per property',
                'type' => 'number',
                'is_public' => true
            ],
            [
                'key' => 'max_property_videos',
                'value' => '3',
                'description' => 'Maximum number of videos per property',
                'type' => 'number',
                'is_public' => true
            ],
            [
                'key' => 'support_phone',
                'value' => '+2348012345678',
                'description' => 'Customer support phone number',
                'type' => 'string',
                'is_public' => true
            ],
            [
                'key' => 'support_email',
                'value' => 'support@shelterbaze.com',
                'description' => 'Customer support email',
                'type' => 'string',
                'is_public' => true
            ]
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}