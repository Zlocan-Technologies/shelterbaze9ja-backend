<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Register a new user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|unique:users|regex:/^\+234[0-9]{10}$/',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:user,landlord,agent'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password' => $request->password, // Mutator handles hashing
                'role' => $request->role,
            ]);

            // Create user profile
            $user->profile()->create();

            // Log the registration
            AuditLog::log('user_registered', $user);

            // Send welcome email
            $this->notificationService->sendEmail(
                $user->email,
                'Welcome to Shelterbaze',
                'Welcome to Shelterbaze! Please verify your email and complete your profile to get started.'
            );

            // Create notification
            $this->notificationService->createInAppNotification(
                $user->id,
                'Welcome to Shelterbaze!',
                'Please complete your profile verification to access all features.',
                'info'
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user->load('profile'),
                    'token' => $token
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();

        // Check if account is active
        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active. Please contact support.',
                'data' => ['account_status' => $user->account_status]
            ], 423);
        }

        // Log the login
        AuditLog::log('user_login', $user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('profile'),
                'token' => $token
            ]
        ]);
    }

    public function logout(Request $request)
    {
        try {
            // Log the logout
            AuditLog::log('user_logout', $request->user());

            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
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

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $status = Password::sendResetLink($request->only('email'));

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent to your email'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => $password
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    // Log password reset
                    AuditLog::log('password_reset', $user);
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Password reset failed'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load([
                    'profile',
                ]),
                'unreadNotificationsCount' => $user->unreadNotificationsCount
            ]
        ]);
    }

    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();
            
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
            
            // Create new token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => ['token' => $token]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}