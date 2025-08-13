<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            SystemSettingsSeeder::class,
            AdminUserSeeder::class,
            StatesAndLgasSeeder::class,
        ]);
    }
}
