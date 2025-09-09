<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\CreateProfileRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\InitiateCreateProfileRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Http\Request;

interface IAuthService
{

    public function sendOnboardingOtp(InitiateCreateProfileRequest $request);

    public function verifyOtp(string $email, string $code);

    public function login(LoginRequest $request);

    public function register(CreateProfileRequest $request);

    public function logout(Request $request);

    public function refreshToken(Request $request);

    public function getUserProfile(Request $request);

    public function forgotPassword(ForgotPasswordRequest $request);
    
    public function resetPassword(ResetPasswordRequest $request);
}
