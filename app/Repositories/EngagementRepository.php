<?php

namespace App\Repositories;

use App\Http\Requests\Engagement\InitiatePaymentRequest;
use App\Http\Requests\Engagement\VerifyPaymentRequest;
use App\Models\AuditLog;
use App\Models\EngagementFee;
use App\Models\Property;
use App\Models\SystemSetting;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Util\ApiResponse;
use Illuminate\Http\Request;

class EngagementRepository
{

    public function __construct(private PaymentService $paymentService, private NotificationService $notificationService) {}


    public function initiateEngagement(InitiatePaymentRequest $request)
    {
        $user = $request->user();
        $property = Property::findOrFail($request->property_id);

        // Check if user already paid engagement fee for this property
        $existingFee = EngagementFee::where('user_id', $user->id)
            ->where('property_id', $property->id)
            ->first();

        if ($existingFee && $existingFee->isCompleted()) {
            return ApiResponse::respond(
                message: 'Engagement fee already paid for this property',
                status: false,
                statusCode: 400
            );
        }

        // Get engagement fee amount from settings
        $engagementFee = SystemSetting::get('engagement_fee', 5000);

        // Generate payment reference
        $reference = $this->paymentService->generatePaymentReference('ENG');

        // Create or update engagement fee record
        $engagementFeeRecord = EngagementFee::updateOrCreate(
            [
                'user_id' => $user->id,
                'property_id' => $property->id
            ],
            [
                'amount' => $engagementFee,
                'payment_reference' => $reference,
                'payment_status' => 'pending',
                'payment_method' => 'paystack'
            ]
        );

        // Initialize Paystack payment
        $this->paymentService->setCallbackUrl(route('engagement.verify', ['reference' => $reference]));
        $paymentData = $this->paymentService->initializePayment(
            $engagementFee,
            $user->email,
            $reference,
            [
                'user_id' => $user->id,
                'property_id' => $property->id,
                'property_title' => $property->title,
                'engagement_fee_id' => $engagementFeeRecord->id
            ]
        );

        // Log payment initiation
        AuditLog::log('engagement_payment_initiated', $engagementFeeRecord);

        return ApiResponse::respond(
            message: 'Payment initialized successfully',
            data: [
                'payment_url' => $paymentData['data']['authorization_url'] ?? '',
                'reference' => $reference,
                'amount' => $engagementFee,
                'property' => $property->only(['id', 'title'])
            ]
        );
    }

    public function verifyPayment(VerifyPaymentRequest $request)
    {
        $engagementFee = EngagementFee::where('payment_reference', $request->reference)->first();

        if ($engagementFee->isCompleted()) {
            return ApiResponse::respond(
                message: 'Payment already verified',
                data: $engagementFee->load('property')
            );
        }

        // Verify payment with Paystack
        $paymentDetails = $this->paymentService->verifyPayment($request->reference);

        if ($paymentDetails['status'] && $paymentDetails['data']['status'] === 'success') {
            // Update engagement fee record
            $engagementFee->update([
                'payment_status' => 'completed',
                'payment_data' => $paymentDetails['data'],
                'paid_at' => now()
            ]);

            // Log successful payment
            AuditLog::log('engagement_payment_completed', $engagementFee);

            // Create notification for user
            $this->notificationService->createInAppNotification(
                $engagementFee->user_id,
                'Payment Successful',
                "Engagement fee payment for '{$engagementFee->property->title}' was successful. You can now view contact details.",
                'success'
            );

            // Create notification for landlord
            $this->notificationService->createInAppNotification(
                $engagementFee->property->landlord_id,
                'New Interested Tenant',
                "A user has paid engagement fee for your property '{$engagementFee->property->title}'.",
                'info'
            );

            return ApiResponse::respond(
                message: 'Payment verified successfully',
                data: $engagementFee->load('property')
            );
        } else {
            $engagementFee->markAsFailed();

            return ApiResponse::respond(
                message: 'Payment verification failed',
                status: false,
                statusCode: 400
            );
        }
    }

    public function getPropertyContact(Request $request, $propertyId)
    {
        $user = $request->user();
        $property = Property::with(['landlord', 'agent'])->findOrFail($propertyId);

        // Check if user has paid engagement fee
        if (
            !$property->hasUserPaidEngagementFee($user->id) &&
            $user->id !== $property->landlord_id &&
            !$user->isAdmin()
        ) {
            return ApiResponse::respond(
                message: 'Engagement fee required to view contact details',
                status: false,
                statusCode: 402
            );
        }

        $contactData = [
            'landlord' => [
                'name' => $property->landlord->full_name,
                'email' => $property->landlord->email,
                'phone' => $property->landlord->phone_number
            ]
        ];

        if ($property->agent) {
            $contactData['agent'] = [
                'name' => $property->agent->full_name,
                'email' => $property->agent->email,
                'phone' => $property->agent->phone_number,
                'agent_id' => $property->agent->profile->agent_id ?? null
            ];
        }

        return ApiResponse::respond(
            message: 'Contact details retrieved successfully',
            data: $contactData
        );
    }

    public function myEngagements(Request $request)
    {
        $user = $request->user();

        $engagements = EngagementFee::with(['property.media', 'property.landlord:id,first_name,last_name'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return ApiResponse::respond(
            data: $engagements,
            message: 'Engagements retrieved successfully'
        );
    }

    public function getInterestedTenants(Request $request, $propertyId)
    {
        $user = $request->user();
        $property = Property::findOrFail($propertyId);

        // Check authorization - only landlord, assigned agent, or admin can view
        if (
            $property->landlord_id !== $user->id &&
            $property->agent_id !== $user->id &&
            !$user->isAdmin()
        ) {
            return ApiResponse::respond(
                message: 'Unauthorized to view interested tenants',
                status: false,
                statusCode: 403
            );
        }

        $interestedTenants = $property->getInterestedTenants()
            ->with(['profile'])
            ->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'created_at')
            ->paginate($request->get('per_page', 15));

        return ApiResponse::respond(
            message: 'Interested tenants retrieved successfully',
            data: $interestedTenants
        );
    }
}
