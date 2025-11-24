<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeeSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create tenant commission percentage setting
        SystemSetting::updateOrCreate(
            ['key' => 'tenant_commission_percentage'],
            [
                'value' => env('TENANT_COMMISSION_PERCENTAGE', 10),
                'description' => 'Commission percentage charged to tenants (added to rent amount)',
                'type' => SystemSetting::TYPE_NUMBER,
                'is_public' => true
            ]
        );

        // Create landlord management fee percentage setting
        SystemSetting::updateOrCreate(
            ['key' => 'landlord_management_fee_percentage'],
            [
                'value' => env('LANDLORD_MANAGEMENT_FEE_PERCENTAGE', 10),
                'description' => 'Management fee percentage deducted from landlord payout',
                'type' => SystemSetting::TYPE_NUMBER,
                'is_public' => false
            ]
        );
    }
}
