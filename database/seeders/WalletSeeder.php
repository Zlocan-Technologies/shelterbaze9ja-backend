<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //create wallets for all landlords and agents
        $users = User::whereIn('role', [User::ROLE_LANDLORD, User::ROLE_AGENT])->get();

        foreach($users as $user) {
            //if user does not have a wallet, create one
            if(!$user->wallet) {
                $user->wallet()->create();
            }
        }
    }
}
