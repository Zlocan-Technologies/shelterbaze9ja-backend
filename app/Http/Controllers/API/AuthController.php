<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CreateProfileRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\InitiateCreateProfileRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Otp\VerifyOtpRequest;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\Auth\AuthService;
use App\Services\NotificationService;
use App\Util\ApiResponse;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private $notificationService;

    public function __construct(NotificationService $notificationService, protected AuthService $authService)
    {
        $this->notificationService = $notificationService;
    }


    public function sendOnboardingOtp(InitiateCreateProfileRequest $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->authService->sendOnboardingOtp($request));
    }


    /**
     * Register a new user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function register(CreateProfileRequest $request)
    {
        return (new ResponseHandler())->execute(fn()  => $this->authService->register($request));
    }

    public function login(LoginRequest $request)
    {
        return (new ResponseHandler())->execute(fn()  => $this->authService->login($request));
    }

    public function logout(Request $request)
    {
        return (new ResponseHandler())->execute(fn()  => $this->authService->logout($request));
    }

    public function sendPhoneVerification(Request $request)
    {
        $user = $request->user();

        if ($user->phone_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already verified'
            ], 400);
        }

        try {
            $verificationCode = rand(100000, 999999);

            // Store verification code (you might want to use cache or database)
            cache(['phone_verification_' . $user->id => $verificationCode], now()->addMinutes(10));

            // Send SMS
            $smsResult = $this->notificationService->sendSMS(
                $user->phone_number,
                "Your Shelterbaze verification code is: {$verificationCode}"
            );

            if (!$smsResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification SMS'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Verification code sent successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $storedCode = cache('phone_verification_' . $user->id);

        if (!$storedCode || $storedCode != $request->verification_code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code'
            ], 400);
        }

        try {
            $user->update(['phone_verified_at' => now()]);

            // Clear the verification code
            cache()->forget('phone_verification_' . $user->id);

            // Log the verification
            AuditLog::log('phone_verified', $user);

            // Create notification
            $this->notificationService->createInAppNotification(
                $user->id,
                'Phone Verified',
                'Your phone number has been successfully verified.',
                'success'
            );

            return response()->json([
                'success' => true,
                'message' => 'Phone number verified successfully',
                'data' => ['user' => $user->fresh()]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->authService->forgotPassword($request));
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
       return (new ResponseHandler())->execute(fn () => $this->authService->resetPassword($request));
    }

    public function me(Request $request)
    {
        return (new ResponseHandler())->execute(fn()  => $this->authService->getUserProfile($request));
    }

    public function refreshToken(Request $request)
    {
        return (new ResponseHandler())->execute(fn()  => $this->authService->refreshToken($request));
    }
}
