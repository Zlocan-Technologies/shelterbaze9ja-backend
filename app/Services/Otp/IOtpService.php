<?php

namespace App\Services\Otp;

use App\Models\OtpVerification;

interface IOtpService
{
    public function getOtpData($email): ?OtpVerification;

    public function deleteOtpData($email): void;

    public function validateOtp(OtpVerification $otp, $value): bool;

    public function createOtp(string $email): string;

    public function updateOtpStatus(OtpVerification $otp, bool $status = false): bool;
}
