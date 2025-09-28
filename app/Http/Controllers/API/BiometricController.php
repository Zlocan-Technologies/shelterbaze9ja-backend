<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BiometricAuthRequest;
use App\Http\Requests\BiometricEnrollRequest;
use App\Models\User;
use App\Services\Auth\BiometricService;
use App\Util\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BiometricController extends Controller
{
    public function __construct(
        private BiometricService $biometricService
    ) {}

    /**
     * Enroll user for biometric authentication
     * 
     * @param BiometricEnrollRequest $request
     * @return JsonResponse
     */
    public function enroll(BiometricEnrollRequest $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return ApiResponse::respond(
                status: false,
                message: 'User not authenticated',
                statusCode: 401
            );
        }

        // Check if biometric is already enabled
        if ($user->biometric_enabled) {
            return ApiResponse::respond(
                status: false,
                message: 'Biometric authentication is already enabled for this account',
                statusCode: 409,
                errors: ['Biometric already enrolled']
            );
        }

        return $this->biometricService->enrollBiometric(
            user: $user,
            biometricData: $request->input('biometric_data'),
            deviceId: $request->input('device_id')
        );
    }

    /**
     * Authenticate user using biometric data
     * 
     * @param BiometricAuthRequest $request
     * @return JsonResponse
     */
    public function authenticate(BiometricAuthRequest $request): JsonResponse
    {
        return $this->biometricService->authenticateBiometric(
            email: $request->input('email'),
            biometricData: $request->input('biometric_data'),
            deviceId: $request->input('device_id')
        );
    }

    /**
     * Disable biometric authentication for the authenticated user
     * 
     * @return JsonResponse
     */
    public function disable(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return ApiResponse::respond(
                status: false,
                message: 'User not authenticated',
                statusCode: 401
            );
        }

        if (!$user->biometric_enabled) {
            return ApiResponse::respond(
                status: false,
                message: 'Biometric authentication is not enabled for this account',
                statusCode: 409,
                errors: ['Biometric not enrolled']
            );
        }

        return $this->biometricService->disableBiometric($user);
    }

    /**
     * Get biometric status for the authenticated user
     * 
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return ApiResponse::respond(
                status: false,
                message: 'User not authenticated',
                statusCode: 401
            );
        }

        return $this->biometricService->getBiometricStatus($user);
    }

    /**
     * Get biometric authentication attempts for security monitoring
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function attempts(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return ApiResponse::respond(
                status: false,
                message: 'User not authenticated',
                statusCode: 401
            );
        }

        $hours = $request->input('hours', 24);
        
        if ($hours > 168) { // Max 7 days
            $hours = 168;
        }

        return $this->biometricService->getAuthenticationAttempts($user, $hours);
    }

    /**
     * Re-enroll biometric data (update existing enrollment)
     * 
     * @param BiometricEnrollRequest $request
     * @return JsonResponse
     */
    public function reenroll(BiometricEnrollRequest $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return ApiResponse::respond(
                status: false,
                message: 'User not authenticated',
                statusCode: 401
            );
        }

        if (!$user->biometric_enabled) {
            return ApiResponse::respond(
                status: false,
                message: 'Biometric authentication is not currently enabled. Use the enroll endpoint instead.',
                statusCode: 409,
                errors: ['No existing enrollment found']
            );
        }

        // Disable current biometric first
        $this->biometricService->disableBiometric($user);
        
        // Re-enroll with new data
        $refreshedUser = User::find($user->id);
        return $this->biometricService->enrollBiometric(
            user: $refreshedUser,
            biometricData: $request->input('biometric_data'),
            deviceId: $request->input('device_id')
        );
    }
}