<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // Create admin user
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@shelterbaze.com',
            'phone_number' => '+2348000000000',
            'password' => 'admin123456', //hashed automatically
            'role' => 'admin',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'profile_completed' => true,
            'account_status' => 'active'
        ]);

        // Create admin profile
        UserProfile::create([
            'user_id' => $admin->id,
            'nin_number' => '12345678901',
            'address' => 'Admin Office, Lagos',
            'state' => 'Lagos',
            'lga' => 'Lagos Island'
        ]);

        // Create test landlord
        $landlord = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'landlord@example.com',
            'phone_number' => '+2348111111111',
            'password' => 'password123',
            'role' => 'landlord',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'profile_completed' => true,
            'account_status' => 'active'
        ]);

        UserProfile::create([
            'user_id' => $landlord->id,
            'nin_number' => '12345678902',
            'address' => 'Victoria Island, Lagos',
            'state' => 'Lagos',
            'lga' => 'Lagos Island'
        ]);

        // Create test agent
        $agent = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'agent@example.com',
            'phone_number' => '+2348222222222',
            'password' => 'password123',
            'role' => 'agent',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'profile_completed' => true,
            'account_status' => 'active'
        ]);

        UserProfile::create([
            'user_id' => $agent->id,
            'nin_number' => '12345678903',
            'address' => 'Ikeja, Lagos',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
            'agent_id' => 'AGT001'
        ]);

        // Create test user
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user@example.com',
            'phone_number' => '+2348333333333',
            'password' => 'password123',
            'role' => 'user',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'profile_completed' => true,
            'account_status' => 'active'
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'nin_number' => '12345678904',
            'address' => 'Surulere, Lagos',
            'state' => 'Lagos',
            'lga' => 'Surulere'
        ]);
    }
}