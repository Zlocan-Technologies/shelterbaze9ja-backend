<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\CompleteProfileRequest;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\AuditLog;
use App\Repositories\ProfileRepository;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{

    public function __construct(
        private FileUploadService $fileUploadService, 
        private NotificationService $notificationService,
        private ProfileRepository $profileRepository)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'data' => ['user' => $user]
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $request->user()->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $oldData = $user->toArray();

            $user->update($request->only(['first_name', 'last_name', 'phone_number']));

            // If phone number changed, reset verification
            if ($request->has('phone_number') && $request->phone_number !== $user->getOriginal('phone_number')) {
                $user->update(['phone_verified_at' => null]);
            }

            // Log the update
            AuditLog::log('profile_updated', $user, $oldData, $user->fresh()->toArray());

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => ['user' => $user->fresh()->load('profile')]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function completeProfile(CompleteProfileRequest $request)
    {
       return (new ResponseHandler())->execute(fn() => $this->profileRepository->completeProfile($request));
    }

    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
            'document_type' => 'required|string|in:id_card,utility_bill,bank_statement',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Upload document
            $documentUpload = $this->fileUploadService->uploadToCloudinary(
                $request->file('document'),
                'profiles/documents'
            );

            if (!$documentUpload['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload document',
                    'error' => $documentUpload['error']
                ], 500);
            }

            // Update verification documents
            $verificationDocs = $user->profile->verification_documents ?? [];
            $verificationDocs[$request->document_type] = $documentUpload['url'];

            $user->profile()->update([
                'verification_documents' => $verificationDocs
            ]);

            // Log document upload
            AuditLog::log('document_uploaded', $user, null, [
                'document_type' => $request->document_type,
                'document_url' => $documentUpload['url']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'document_url' => $documentUpload['url'],
                    'document_type' => $request->document_type
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Document upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAgentIdCard(Request $request)
    {
        $user = $request->user();

        if (!$user->isAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. User is not an agent.'
            ], 403);
        }

        if (!$user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Agent account not active'
            ], 423);
        }

        try {
            $profile = $user->profile;

            if (!$profile->agent_id) {
                $profile->generateAgentId();
                $profile = $profile->fresh();
            }

            // Generate ID card data
            $idCardData = [
                'agent_id' => $profile->agent_id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone_number,
                'address' => $profile->full_address,
                'verification_status' => $user->account_status,
                'issue_date' => $user->created_at->format('Y-m-d'),
                'profile_image' => $profile->nin_selfie_url
            ];

            return response()->json([
                'success' => true,
                'data' => ['id_card' => $idCardData]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate ID card',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->update(['password' => $request->new_password]);

            // Log password change
            AuditLog::log('password_changed', $user);

            // Create notification
            $this->notificationService->createInAppNotification(
                $user->id,
                'Password Changed',
                'Your password has been successfully changed.',
                'success'
            );

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}