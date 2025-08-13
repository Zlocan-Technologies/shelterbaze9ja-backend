<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\EngagementFee;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use App\Services\PaymentService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EngagementController extends Controller
{
    private $paymentService;
    private $notificationService;

    public function __construct(PaymentService $paymentService, NotificationService $notificationService)
    {
        $this->paymentService = $paymentService;
        $this->notificationService = $notificationService;
    }

    public function initiatePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id'
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
            $property = Property::findOrFail($request->property_id);

            // Check if user already paid engagement fee for this property
            $existingFee = EngagementFee::where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->first();

            if ($existingFee && $existingFee->isCompleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Engagement fee already paid for this property'
                ], 400);
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
            $paymentData = $this->paymentService->initializePaystackPayment(
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

            return response()->json([
                'success' => true,
                'message' => 'Payment initialized successfully',
                'data' => [
                    'payment_url' => $paymentData['authorizationUrl'],
                    'reference' => $reference,
                    'amount' => $engagementFee,
                    'property' => $property->only(['id', 'title'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment initialization failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string|exists:engagement_fees,payment_reference'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $engagementFee = EngagementFee::where('payment_reference', $request->reference)->first();

            if ($engagementFee->isCompleted()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already verified',
                    'data' => ['engagement_fee' => $engagementFee->load('property')]
                ]);
            }

            // Verify payment with Paystack
            $paymentDetails = $this->paymentService->verifyPaystackPayment($request->reference);

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

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'data' => ['engagement_fee' => $engagementFee->load('property')]
                ]);

            } else {
                $engagementFee->markAsFailed();

                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed'
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPropertyContact($propertyId, Request $request)
    {
        try {
            $user = $request->user();
            $property = Property::with(['landlord', 'agent'])->findOrFail($propertyId);

            // Check if user has paid engagement fee
            if (!$property->hasUserPaidEngagementFee($user->id) && 
                $user->id !== $property->landlord_id && 
                !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Engagement fee required to view contact details'
                ], 402); // Payment Required
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

            return response()->json([
                'success' => true,
                'data' => $contactData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get contact details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function myEngagements(Request $request)
    {
        try {
            $user = $request->user();

            $engagements = EngagementFee::with(['property.media', 'property.landlord:id,first_name,last_name'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $engagements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch engagements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getInterestedTenants($propertyId, Request $request)
    {
        try {
            $user = $request->user();
            $property = Property::findOrFail($propertyId);

            // Check authorization - only landlord, assigned agent, or admin can view
            if ($property->landlord_id !== $user->id && 
                $property->agent_id !== $user->id && 
                !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view interested tenants'
                ], 403);
            }

            $interestedTenants = $property->getInterestedTenants()
                ->with(['profile'])
                ->select('id', 'first_name', 'last_name', 'email', 'phone_number', 'created_at')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $interestedTenants
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch interested tenants',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}