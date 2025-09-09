<?php

namespace App\Repositories;

use App\Http\Requests\Profile\CompleteProfileRequest;
use App\Models\AuditLog;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Traits\SendMail;
use App\Util\ApiResponse;

class ProfileRepository
{

    use SendMail;

    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService
    ) {}

    public function completeProfile(CompleteProfileRequest $request)
    {
        $user = $request->user();

        if ($user->profile_completed) {
            return ApiResponse::respond(
                status: false,
                message: 'Profile already completed',
                statusCode: 400
            );
        }

        // Upload NIN selfie
        $ninSelfieUpload = $this->fileUploadService->uploadToCloudinary(
            $request->file('nin_selfie'),
            'profiles/nin_selfies'
        );

        if (!$ninSelfieUpload['success']) {
            return ApiResponse::respond(
                status: false,
                message: 'Failed to upload NIN selfie',
                error: $ninSelfieUpload['error'],
                statusCode: 500,
            );
        }

        // Update user profile
        $user->profile()->update([
            'nin_number' => $request->nin_number,
            'nin_selfie_url' => $ninSelfieUpload['url'],
            'address' => $request->address,
            'state' => $request->state,
            'lga' => $request->lga,
        ]);

        // Generate agent ID if user is agent
        if ($user->isAgent()) {
            $user->profile->generateAgentId();
        }

        // Mark profile as completed
        $user->update(['profile_completed' => true]);

        // Log profile completion
        AuditLog::log('profile_completed', $user);

        // Create notification
        $this->notificationService->createInAppNotification(
            $user->id,
            'Profile Completed',
            'Your profile has been completed and is under review.',
            'success'
        );

        // Send email notification to admin for review
        $this->sendToEmail(
            email: config('mail.admin_email', 'admin@shelterbaze.com'),
            subject: 'New Profile Verification Required',
            view: 'email.admin_user_profile_completed',
            user: $user
        );

        return ApiResponse::respond(
            data:  $user->fresh()->load('profile'),
            status: true,
            message: 'Profile completed successfully. Your account is now under review.',
        );
    }
}
