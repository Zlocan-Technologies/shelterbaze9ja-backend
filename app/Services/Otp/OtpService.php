<?php


namespace App\Services\Otp;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Hash;

class OtpService implements IOtpService
{
    private int $otpExpiryMinutes = 10;

    public function getOtpData($email): ?OtpVerification
    {
        $model = OtpVerification::where('email', $email)->first();
        if (!$model) {
            return null;
        }
        return $model;
    }

    public function deleteOtpData($email): void
    {
        OtpVerification::where([
            'email' => $email,
        ])->delete();
    }

    public function validateOtp(OtpVerification $otp, $value): bool
    {
        if (Hash::check($value, $otp->code)) {
            return true;
        }
        return false;
    }

    public function createOtp(string $email): string
    {
        $code = $this->generateOtp();
        OtpVerification::updateOrCreate([
            'email' => $email,
        ], [
            'email' => $email,
            'code' => Hash::make($code), //hash this
            'expiry_time' => now()->addMinutes($this->otpExpiryMinutes)
        ]);
        return $code;
    }

    public function updateOtpStatus(OtpVerification $otp, bool $status = false): bool
    {
        $otp->update(['status' => $status,]);
        return true;
    }

    private function generateOtp(int $otpDigits = 6)
    {
        $otpDigits = max(1, (int)$otpDigits);

        // Calculate the minimum and maximum values based on the number of digits
        $min = pow(10, $otpDigits - 1); // 10^(otpDigits-1)
        $max = pow(10, $otpDigits) - 1; // 10^otpDigits - 1

        // Generate the OTP within the range
        $code = mt_rand($min, $max);
        return $code;
    }
}
