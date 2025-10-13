<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\CompleteProfileRequest;
use App\Http\Requests\Profile\CreateTransactionPinRequest;
use App\Http\Requests\Profile\CreateWithdrawalRequest;
use App\Http\Requests\Profile\ResetTransactionPinRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadDocumentRequest;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\AuditLog;
use App\Repositories\ProfileRepository;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ApiResponse;
use App\Util\ErrorHandler;
use App\Util\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{

    use ErrorHandler;

    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService,
        private ProfileRepository $profileRepository
    ) {}

    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'data' => ['user' => $user]
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->updateProfile($request));
    }

    public function completeProfile(CompleteProfileRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->completeProfile($request));
    }

    public function uploadDocument(UploadDocumentRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->uploadDocument($request));
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

    public function changePassword(ChangePasswordRequest $request)
    {
      return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->changePassword($request));
    }

    public function createTransactionPin(CreateTransactionPinRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->createTransactionPin($request));
    }

    public function forgotTransactionPin(Request $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->forgotTransactionPin($request));
    }

    public function resetTransactionPin(ResetTransactionPinRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->resetTransactionPin($request));
    }

    public function requestWithdrawal(CreateWithdrawalRequest $request)
    {
        return (new ResponseHandler())->executeTransaction(fn() => $this->profileRepository->createWithdrawalRequest($request));
    }

    public function getWithdrawalHistory(Request $request)
    {
        return (new ResponseHandler())->execute(fn() => $this->profileRepository->getWithdrawalHistory($request));
    }
}
