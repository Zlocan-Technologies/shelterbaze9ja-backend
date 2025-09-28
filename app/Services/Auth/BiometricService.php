<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\NotificationService;
use App\Traits\SendMail;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BiometricService
{
    use SendMail;

    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Enroll user for biometric authentication
     */
    public function enrollBiometric(User $user, array $biometricData, string $deviceId): JsonResponse
    {
        try {
            // Hash the biometric template for security
            $hashedBiometricData = [
                'template_hash' => Hash::make($biometricData['template']),
                'type' => $biometricData['type'] ?? 'fingerprint', // fingerprint, face, etc.
                'version' => $biometricData['version'] ?? '1.0',
                'enrolled_at' => now()->toISOString()
            ];

            $user->update([
                'biometric_data' => $hashedBiometricData,
                'biometric_enabled' => true,
                'biometric_enrolled_at' => now(),
                'device_id' => $deviceId
            ]);

            // Log the enrollment
            Log::info('Biometric enrollment successful', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'biometric_type' => $hashedBiometricData['type']
            ]);

            // Send notification
            $this->notificationService->sendCompleteNotification(
                userId: $user->id,
                title: 'Biometric Authentication Enabled',
                message: 'You have successfully enrolled for biometric authentication.',
                options: ['type' => 'security']
            );

            return ApiResponse::respond(
                status: true,
                message: 'Biometric enrollment successful',
                statusCode: 200,
                data: [
                    'biometric_enabled' => true,
                    'enrolled_at' => $user->biometric_enrolled_at,
                    'biometric_type' => $hashedBiometricData['type']
                ]
            );

        } catch (Exception $e) {
            Log::error('Biometric enrollment failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::respond(
                status: false,
                message: 'Biometric enrollment failed',
                statusCode: 500,
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Authenticate user using biometric data
     */
    public function authenticateBiometric(string $email, array $biometricData, string $deviceId): JsonResponse
    {
        try {
            $user = User::where('email', $email)
                ->where('biometric_enabled', true)
                ->where('device_id', $deviceId)
                ->first();

            if (!$user) {
                return ApiResponse::respond(
                    status: false,
                    message: 'Biometric authentication not available for this account',
                    statusCode: 404,
                    errors: ['User not found or biometric not enrolled']
                );
            }

            // Verify biometric template
            if (!$this->verifyBiometricTemplate($user, $biometricData)) {
                Log::warning('Biometric authentication failed - template mismatch', [
                    'user_id' => $user->id,
                    'device_id' => $deviceId
                ]);

                return ApiResponse::respond(
                    status: false,
                    message: 'Biometric authentication failed',
                    statusCode: 401,
                    errors: ['Biometric verification failed']
                );
            }

            // Check account status
            if ($user->account_status !== 'active') {
                return ApiResponse::respond(
                    status: false,
                    message: 'Account is not active',
                    statusCode: 403,
                    errors: ['Your account status is: ' . $user->account_status]
                );
            }

            // Generate token
            $token = $user->createToken('biometric-auth', ['*'], now()->addDays(30))->plainTextToken;

            // Log successful authentication
            Log::info('Biometric authentication successful', [
                'user_id' => $user->id,
                'device_id' => $deviceId
            ]);

            return ApiResponse::respond(
                status: true,
                message: 'Authentication successful',
                statusCode: 200,
                data: [
                    'user' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'account_status' => $user->account_status,
                        'profile_completed' => $user->profile_completed,
                        'biometric_enabled' => $user->biometric_enabled
                    ],
                    'token' => $token
                ]
            );

        } catch (Exception $e) {
            Log::error('Biometric authentication error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::respond(
                status: false,
                message: 'Authentication failed',
                statusCode: 500,
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Disable biometric authentication for user
     */
    public function disableBiometric(User $user): JsonResponse
    {
        try {
            $user->update([
                'biometric_data' => null,
                'biometric_enabled' => false,
                'biometric_enrolled_at' => null,
                'device_id' => null
            ]);

            // Revoke biometric tokens
            $user->tokens()->where('name', 'biometric-auth')->delete();

            Log::info('Biometric authentication disabled', ['user_id' => $user->id]);

            // Send notification
            $this->notificationService->sendCompleteNotification(
                userId: $user->id,
                title: 'Biometric Authentication Disabled',
                message: 'Biometric authentication has been disabled for your account.',
                options: ['type' => 'security']
            );

            return ApiResponse::respond(
                status: true,
                message: 'Biometric authentication disabled successfully',
                statusCode: 200,
                data: ['biometric_enabled' => false]
            );

        } catch (Exception $e) {
            Log::error('Failed to disable biometric authentication', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::respond(
                status: false,
                message: 'Failed to disable biometric authentication',
                statusCode: 500,
                errors: [$e->getMessage()]
            );
        }
    }

    /**
     * Check if biometric is available for user
     */
    public function getBiometricStatus(User $user): JsonResponse
    {
        return ApiResponse::respond(
            status: true,
            message: 'Biometric status retrieved',
            statusCode: 200,
            data: [
                'biometric_enabled' => $user->biometric_enabled,
                'enrolled_at' => $user->biometric_enrolled_at,
                'biometric_type' => $user->biometric_data['type'] ?? null,
                'device_registered' => !empty($user->device_id)
            ]
        );
    }

    /**
     * Verify biometric template against stored hash
     */
    private function verifyBiometricTemplate(User $user, array $biometricData): bool
    {
        if (!$user->biometric_data || !isset($user->biometric_data['template_hash'])) {
            return false;
        }

        // Verify the biometric template hash
        return Hash::check($biometricData['template'], $user->biometric_data['template_hash']);
    }

    /**
     * Get biometric authentication attempts for security monitoring
     */
    public function getAuthenticationAttempts(User $user, int $hours = 24): JsonResponse
    {
        // This would require a separate table to track attempts
        // For now, return basic info
        return ApiResponse::respond(
            status: true,
            message: 'Authentication attempts retrieved',
            statusCode: 200,
            data: [
                'user_id' => $user->id,
                'biometric_enabled' => $user->biometric_enabled,
                'last_enrolled' => $user->biometric_enrolled_at,
                'message' => 'Detailed attempt tracking requires additional implementation'
            ]
        );
    }
}