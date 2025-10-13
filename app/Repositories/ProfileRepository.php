<?php

namespace App\Repositories;

use App\Enums\WithdrawalStatus;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\CompleteProfileRequest;
use App\Http\Requests\Profile\CreateTransactionPinRequest;
use App\Http\Requests\Profile\CreateWithdrawalRequest;
use App\Http\Requests\Profile\ResetTransactionPinRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadDocumentRequest;
use App\Models\AuditLog;
use App\Models\Withdrawal;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\Otp\OtpService;
use App\Traits\SendMail;
use App\Util\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $oldData = $user->toArray();

        $user->update($request->only(['first_name', 'last_name', 'phone_number']));

        // Upload Profile Picture
        if ($request->hasFile('profile_picture')) {
            $profilePicUpload = $this->fileUploadService->uploadToCloudinary(
                $request->file('profile_picture'),
                'profiles/profile_pictures'
            );

            if (!$profilePicUpload['success']) {
                return ApiResponse::respond(
                    status: false,
                    message: 'Failed to upload profile picture',
                    error: $profilePicUpload['error'],
                    statusCode: 500,
                );
            }

            // Update profile picture URL
            $user->profile()->update([
                'profile_picture' => $profilePicUpload['url']
            ]);
        }


        // If phone number changed, reset verification
        if ($request->has('phone_number') && $request->phone_number !== $user->getOriginal('phone_number')) {
            $user->update(['phone_verified_at' => null]);
        }

        // Log the update
        AuditLog::log('profile_updated', $user, $oldData, $user->fresh()->toArray());

        return ApiResponse::respond(
            status: true,
            message: 'Profile updated successfully',
            data: ['user' => $user->fresh()->load('profile')]
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

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::respond(
                status: false,
                message: 'Current password is incorrect',
                statusCode: 400
            );
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

        return ApiResponse::respond(
            message: 'Password changed successfully'
        );
    }

    public function createWithdrawalRequest(CreateWithdrawalRequest $request)
    {
        $user = $request->user();
        
        if (!$user->has_transaction_pin) {
            return ApiResponse::respond(
                status: false,
                message: 'Please set your Transaction PIN before making a withdrawal',
                statusCode: 400
            );
        }

        if (!Hash::check($request->trx_pin, $user->transaction_pin)) {
            return ApiResponse::respond(
                status: false,
                message: 'Invalid Transaction PIN',
                statusCode: 400
            );
        }

        // Validate user has sufficient balance
        if ($user->wallet->balance < $request->amount) {
            return ApiResponse::respond(
                status: false,
                message: 'Insufficient balance',
                statusCode: 400
            );
        }

        // Create withdrawal request
        $withdrawal = Withdrawal::create([
            'user_id' => $user->id,
            'amount' => $request->amount,
            'status' => WithdrawalStatus::PENDING,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'bank_name' => $request->bank_name,
        ]);

        // Log withdrawal request
        AuditLog::log('withdrawal_requested', $user, null, [
            'withdrawal_id' => $withdrawal->id,
            'amount' => $request->amount
        ]);

        return ApiResponse::respond(
            status: true,
            message: 'Withdrawal request created successfully',
            data: $withdrawal
        );
    }

    public function getWithdrawalHistory(Request $request)
    {
        $user = $request->user();

        $withdrawals = $user->withdrawals()->latest()->paginate(20);

        return ApiResponse::respond(
            status: true,
            data: $withdrawals
        );
    }
}
