<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\CreateProfileRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\InitiateCreateProfileRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Otp\VerifyOtpRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Otp\OtpService;
use App\Traits\SendMail;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AuthService implements IAuthService
{
    use SendMail;

    public function __construct(
        private NotificationService $notificationService,
        private OtpService $otpService
    ) {}

    public function sendOnboardingOtp(InitiateCreateProfileRequest $request)
    {
        try {
            DB::beginTransaction();

            // Log the registration
            // AuditLog::log('user_registration_initiated', $request->email);

            $otp = $this->otpService->createOtp($request->email);

            // Send welcome email
            $this->sendToEmail(
                email: $request->email,
                subject: 'Welcome to Shelterbaze',
                otp: $otp
            );

            DB::commit();

            return ApiResponse::respond(data: null, message: "Please check your email for otp!", statusCode: 200, status: true);
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function verifyOtp(string $email, string $code)
    {
        //get otp data
        $otpData = $this->otpService->getOtpData($email);
        if ($otpData != null) {
            if ($this->otpService->validateOtp($otpData, $code)) {
                $this->otpService->updateOtpStatus($otpData, true);
            } else {
                throw new Exception("Invalid OTP", code: 400);
            }
        } else {
            throw new Exception("You have not requested an OTP!", code: 400);
        }
    }

    public function register(CreateProfileRequest $request)
    {

        try {
            DB::beginTransaction();


            $this->verifyOtp($request->email, $request->otp_code);


            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => $request->password, // Mutator handles hashing
                'role' => $request->role,
                'email_verified_at' => now()
            ]);

            // Create user profile
            $user->profile()->create();

            $token = $user->createToken('auth_token')->plainTextToken;

            AuditLog::log('user_registered_successfully', $user);

            // Create notification
            $this->notificationService->createInAppNotification(
                $user->id,
                'Welcome to Shelterbaze!',
                'Please complete your profile verification to access all features.',
                'info'
            );

            DB::commit();

            return ApiResponse::respond(data: [
                'user' => $user->load('profile'),
                'token' => $token
            ], message: "Account verified successfully", statusCode: 200, status: true);
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::respond(
                message: 'Invalid login credentials',
                status: false,
                statusCode: 401
            );
        }

        $user = User::where('email', $request->email)->first();

        // Check if account is active
        // if (!$user->isActive()) {
        //     return ApiResponse::respond(
        //         data: ['account_status' => $user->account_status],
        //         message: "Account is not active. Please contact support.",
        //         statusCode: 423,
        //         status: false
        //     );
        // }

        // Log the login
        AuditLog::log('user_login', $user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::respond(data: [
            'user' => $user->load('profile'),
            'token' => $token
        ], message: "Login successful");
    }

    public function logout(Request $request)
    {
        // Log the logout
        AuditLog::log('user_logout', $request->user());

        $request->user()->currentAccessToken()->delete();

        return ApiResponse::respond(
            message: "Logged out successfully",
        );
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::respond(
            data: ['token' => $token],
            message: "Token refreshed successfully",
        );
    }

    public function getUserProfile(Request $request)
    {
        $user = $request->user();
        return ApiResponse::respond(
            data: [
                'user' => $user->load([
                    'profile',
                ]),
                'unreadNotificationsCount' => $user->unreadNotificationsCount
            ],
            message: "Success!",
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        try {
            DB::beginTransaction();

            $otp = $this->otpService->createOtp($request->email);

            // Send welcome email
            $this->sendToEmail(
                email: $request->email,
                subject: 'Reset your Password',
                otp: $otp,
                view: 'email.resetpassword'
            );

            DB::commit();

            return ApiResponse::respond(data: null, message: "Please check your email for otp!", statusCode: 200, status: true);
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $user = User::where('email', $request->email)->first();
        $user->forceFill([
            'password' => $request->password
        ]); //->setRememberToken(Str::random(60));

        $user->save();
        // Log password reset
        AuditLog::log('password_reset', $user);
        return ApiResponse::respond(data: null, message: "Password reset successfully, you can login now!", statusCode: 200, status: true);
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return ApiResponse::respond(
            data: $user->fresh(),
            message: 'FCM token updated successfully',
        );
    }
}
