<?php

namespace App\Repositories;

use App\Http\Requests\Profile\CompleteProfileRequest;
use App\Http\Requests\Profile\CreateTransactionPinRequest;
use App\Http\Requests\Profile\ResetTransactionPinRequest;
use App\Http\Requests\Profile\UploadDocumentRequest;
use App\Models\AuditLog;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\Otp\OtpService;
use App\Traits\SendMail;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class ProfileRepository
{

    use SendMail;

    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService,
        private OtpService $otpService
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
            data: $user->fresh()->load('profile'),
            status: true,
            message: 'Profile completed successfully. Your account is now under review.',
        );
    }

    public function uploadDocument(UploadDocumentRequest $request)
    {
        $user = $request->user();

        // Upload document
        $documentUpload = $this->fileUploadService->uploadToCloudinary(
            $request->file('document'),
            'profiles/documents'
        );

        if (!$documentUpload['success']) {
            return ApiResponse::respond(
                status: false,
                message: 'Failed to upload document',
                statusCode: 500,
                error: $documentUpload['error']
            );
        }

        // Update verification documents
        $verificationDocs = $user->profile->verification_documents ?? [];
        $verificationDocs[$request->document_type] = $documentUpload['url'];

        $user->profile()->update([
            'verification_documents' => $verificationDocs
        ]);

        if ($request->document_type === 'id_card' && !$user->profile->agent_id) {
            $user->profile()->update([
                'id_card_url' => $documentUpload['url']
            ]);
        }

        // Log document upload
        AuditLog::log('document_uploaded', $user, null, [
            'document_type' => $request->document_type,
            'document_url' => $documentUpload['url']
        ]);

        return ApiResponse::respond(
            status: true,
            message: 'Document uploaded successfully',
            data: [
                'document_url' => $documentUpload['url'],
                'document_type' => $request->document_type
            ]
        );
    }

    public function createTransactionPin(CreateTransactionPinRequest $request)
    {
        $user = $request->user();
        $txnPin = $request->txn_pin;
        if ($user->txn_pin) {
            return ApiResponse::respond(
                status: false,
                message: 'Transaction PIN already set',
                statusCode: 400
            );
        }

        $user->update(['txn_pin' => $txnPin]);

        // Log transaction PIN creation
        AuditLog::log('transaction_pin_created', $user);

        return ApiResponse::respond(
            status: true,
            message: 'Transaction PIN created successfully',
            data: [
                'user' => $user->fresh()->load('profile')
            ]
        );
    }

    public function forgotTransactionPin(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::respond(
                status: false,
                message: 'User not found',
                statusCode: 404
            );
        }

        // Generate OTP and send email
        $otp = $this->otpService->createOtp($user->email);

        // Send welcome email
        $this->sendToEmail(
            email: $user->email,
            subject: 'Reset Your Transaction PIN',
            view: 'email.reset_transaction_pin',
            otp: $otp
        );

        return ApiResponse::respond(
            status: true,
            message: 'OTP sent to email',
        );
    }

    private function verifyOtp(string $email, string $code)
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

    public function resetTransactionPin(ResetTransactionPinRequest $request)
    {
        $user = $request->user();
        $txnPin = $request->txn_pin;

        if (!$user->txn_pin) {
            return ApiResponse::respond(
                status: false,
                message: 'Transaction PIN not set. Please create one first.',
                statusCode: 400
            );
        }

        // Verify OTP
        $this->verifyOtp($user->email, $request->otp_code);

        $user->update(['txn_pin' => $txnPin]);

        // Log transaction PIN reset
        AuditLog::log('transaction_pin_reset', $user);

        return ApiResponse::respond(
            status: true,
            message: 'Transaction PIN reset successfully',
            data: [
                'user' => $user->fresh()->load(['profile', 'wallet'])
            ]
        );
    }
}
