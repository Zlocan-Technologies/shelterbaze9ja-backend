<?php

namespace App\Services\Wallet;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class WalletService
{
    public function fundWallet(User $user, $amount)
    {
        $wallet = Wallet::where('user_id', $user->id)->first();
        if (!$wallet) {
            $user->wallet()->create();
            $wallet = Wallet::where('user_id', $user->id)->first();
        }

        $wallet->balance += $amount;
        $wallet->save();
        AuditLog::log("{$user->id} {$user->name} wallet funded with {$amount}", $wallet);
    }

    public function debitWallet(User $user, $amount)
    {
        $wallet = Wallet::where('user_id', $user->id)->first();
        if (!$wallet) {
            $user->wallet()->create();
            $wallet = Wallet::where('user_id', $user->id)->first();
        }

        $wallet->balance -= $amount;
        $wallet->save();
         AuditLog::log("{$user->id} {$user->name} wallet debited with {$amount}", $wallet);
    }

    public function createWithdrawal(Request $request)
    {
        //check wallet balance with amount
        try {
            $user = $request->user();
            $amount = $request->amount;
            $this->validateWalletBalance($user, $amount);

            $user->withdrawals()->create($request->validated());
            //debit user wallet
            $this->debitWallet($user, $amount);
            return ApiResponse::respond(message: 'Your withdrawal request was successful! Your funds will be credited to your account within 48 working hours');
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    private function validateWalletBalance(User $user, float $amount)
    {
        if ($user->wallet->balance < $amount) {
            throw new Exception("Insufficient Balance");
        }
    }
}
