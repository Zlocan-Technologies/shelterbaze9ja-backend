<?php

namespace App\Repositories;

use App\Http\Requests\Rent\CancelRentalRequest;
use App\Http\Requests\Rent\EarlyTerminationRequest;
use App\Http\Requests\Rent\GenerateInvoiceRequest;
use App\Http\Requests\Rent\RenewalRequest;
use App\Http\Requests\Rent\ReportIssueRequest;
use App\Http\Requests\Rent\UploadPaymentProofRequest;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\RentalAgreement;
use App\Models\RentPayment;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Util\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RentPaymentRepository
{

    public function __construct(
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService
    ) {}

    /**
     * Generate invoice for rent payment
     *  
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateInvoice(GenerateInvoiceRequest $request)
    {

        $user = $request->user();
        $property = Property::with(['landlord', 'agent'])->findOrFail($request->property_id);

        // Check if property is available
        if (!$property->isAvailable()) {
            return ApiResponse::respond(
                message: 'Property is not available for rent',
                status: false,
                statusCode: 400
            );
        }

        // Check if user has paid engagement fee
        if (!$property->hasUserPaidEngagementFee($user->id)) {
            return ApiResponse::respond(
                message: 'Please pay engagement fee first to proceed with rental',
                status: false,
                statusCode: 402
            );
        }

        // Check if user already has active agreement for this property
        $existingAgreement = RentalAgreement::where('property_id', $property->id)
            ->where('tenant_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if ($existingAgreement) {
            return ApiResponse::respond(
                message: 'You already have an active or pending rental agreement for this property',
                status: false,
                statusCode: 400
            );
        }

        // Check user's rental history and limits
        $activeRentals = RentalAgreement::where('tenant_id', $user->id)
            ->where('status', 'active')
            ->count();

        if ($activeRentals >= 5) { // Max 5 active rentals per user
            return response()->json([
                'success' => false,
                'message' => 'You cannot have more than 5 active rental agreements'
            ], 400);
        }

        $startDate = Carbon::parse($request->start_date);
        $endDate = $startDate->copy()->addMonths($request->rental_period_months)->subDay();

        // Calculate amounts based on rental period
        $monthlyRent = $property->rent_amount;
        $rentAmount = $monthlyRent * ($request->rental_period_months);
        $commission = $property->shelterbaze_commission * ($request->rental_period_months);
        $totalAmount = $rentAmount + $commission;

        // Apply discount for long-term rentals (12+ months get 5% discount)
        $discount = 0;
        if ($request->rental_period_months >= 12) {
            $discount = $rentAmount * 0.05; // 5% discount on rent amount
            $rentAmount -= $discount;
            $totalAmount = $rentAmount + $commission;
        }

        // Create rental agreement
        $agreement = RentalAgreement::create([
            'property_id' => $property->id,
            'tenant_id' => $user->id,
            'landlord_id' => $property->landlord_id,
            'agent_id' => $property->agent_id,
            'rent_amount' => $rentAmount,
            'shelterbaze_commission' => $commission,
            'total_amount' => $totalAmount,
            'agreement_start_date' => $startDate,
            'agreement_end_date' => $endDate,
            'status' => 'pending',
            'terms_conditions' => $request->lease_terms
        ]);

        // Get bank details for payment
        $bankDetails = SystemSetting::get('shelterbaze_bank_details', [
            'account_number' => '1234567890',
            'bank_name' => 'First Bank of Nigeria',
            'account_name' => 'Shelterbaze Limited'
        ]);

        // Log invoice generation
        AuditLog::log('rental_invoice_generated', $agreement);

        // Create notifications
        $this->notificationService->createInAppNotification(
            $user->id,
            'Invoice Generated',
            "Rental invoice for '{$property->title}' has been generated. Amount: ₦" . number_format($totalAmount),
            'success'
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $property->landlord_id,
            'New Rental Request',
            "A tenant has requested to rent your property '{$property->title}' for {$request->rental_period_months} months.",
            'info'
        );

        // Notify agent if assigned
        if ($property->agent_id) {
            $this->notificationService->createInAppNotification(
                $property->agent_id,
                'New Rental Request',
                "A rental request has been made for property '{$property->title}' that you manage.",
                'info'
            );
        }

        return ApiResponse::respond(
            data: [
                'agreement' => $agreement->load('property'),
                'bank_details' => $bankDetails,
                'discount_applied' => $discount,
                'payment_instructions' => [
                    'transfer_to_account' => $bankDetails,
                    'reference' => "RENT_{$agreement->id}_{$user->id}",
                    'amount' => $totalAmount,
                    'upload_proof_required' => true,
                    'payment_deadline' => now()->addDays(7)->format('Y-m-d'), // 7 days to pay
                    'note' => 'Upload payment proof after making transfer'
                ],
                'rental_details' => [
                    'monthly_rent' => $monthlyRent,
                    'total_months' => $request->rental_period_months,
                    'rent_amount' => $rentAmount,
                    'commission' => $commission,
                    'discount' => $discount,
                    'total_amount' => $totalAmount,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d')
                ]
            ],
            message: 'Invoice generated successfully'
        );
    }

    /**
     * Upload payment proof for rent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPaymentProof(UploadPaymentProofRequest $request)
    {
        $user = $request->user();
        $agreement = RentalAgreement::with(['property'])
            ->where('tenant_id', $user->id)
            ->find($request->rental_agreement_id);

        if (!$agreement && $request->payment_type == 'offline') {
            $property = Property::with(['landlord', 'agent'])->findOrFail($request->property_id);

            $startDate = Carbon::parse($request->start_date);
            $endDate = $startDate->copy()->addMonths($request->rental_period_months)->subDay();

            // Calculate amounts based on rental period
            $monthlyRent = $property->rent_amount;
            $rentAmount = $monthlyRent * ($request->rental_period_months);
            $commission = $property->shelterbaze_commission * ($request->rental_period_months);
            $totalAmount = $rentAmount + $commission;

            // Apply discount for long-term rentals (12+ months get 5% discount)
            $discount = 0;
            if ($request->rental_period_months >= 12) {
                $discount = $rentAmount * 0.05; // 5% discount on rent amount
                $rentAmount -= $discount;
                $totalAmount = $rentAmount + $commission;
            }

            //create new agreement
            $agreement = RentalAgreement::create([
                'property_id' => $property->id,
                'tenant_id' => $user->id,
                'landlord_id' => $property->landlord_id,
                'agent_id' => $property->agent_id,
                'rent_amount' => $rentAmount,
                'shelterbaze_commission' => $commission,
                'total_amount' => $totalAmount,
                'agreement_start_date' => $startDate,
                'agreement_end_date' => $endDate,
                'status' => 'pending',
                'terms_conditions' => $request->lease_terms
            ]);
        }
        
        if(!$agreement && $request->payment_type == 'online') {
            return ApiResponse::respond(
                message: 'Rental agreement not found',
                status: false,
                statusCode: 404
            );
        }

        // Check if agreement is in valid state for payment
        if (!in_array($agreement->status, ['pending', 'active'])) {
            return ApiResponse::respond(
                message: 'Cannot upload payment proof for this agreement status',
                status: false,
                statusCode: 400
            );
        }

        // Check if there's already a pending payment for this agreement
        $pendingPayment = RentPayment::where('rental_agreement_id', $agreement->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingPayment) {
            return ApiResponse::respond(
                message: 'There is already a pending payment verification for this agreement',
                status: false,
                statusCode: 400
            );
        }

        // Validate payment amount against agreement
        $expectedAmount = $agreement->total_amount;
        $tolerance = 0.02; // 2% tolerance for bank charges
        $minAcceptable = $expectedAmount * (1 - $tolerance);
        $maxAcceptable = $expectedAmount * (1 + $tolerance);

        if ($request->amount < $minAcceptable || $request->amount > $maxAcceptable) {
            return ApiResponse::respond(
                message: "Payment amount should be between ₦" . number_format($minAcceptable) . " and ₦" . number_format($maxAcceptable) . " (expected: ₦" . number_format($expectedAmount) . ")",
                status: false,
                statusCode: 400
            );
        }

        // Upload payment proof
        $proofUpload = $this->fileUploadService->uploadToCloudinary(
            $request->file('payment_proof'),
            'rent_payments/proofs'
        );

        if (!$proofUpload['success']) {
            return ApiResponse::respond(
                message: 'Failed to upload payment proof',
                error: $proofUpload['error'],
                status: false,
                statusCode: 500
            );
        }

        // Get bank details
        $bankDetails = SystemSetting::get('shelterbaze_bank_details', []);

        // Create rent payment record
        $payment = RentPayment::create([
            'rental_agreement_id' => $agreement->id,
            'user_id' => $user->id,
            'amount' => $request->amount,
            'payment_type' => $request->payment_type,
            'bank_account_number' => $bankDetails['account_number'] ?? null,
            'bank_name' => $bankDetails['bank_name'] ?? null,
            'account_name' => $bankDetails['account_name'] ?? null,
            'payment_proof_url' => $proofUpload['url'],
            'payment_date' => $request->payment_date,
            'due_date' => $agreement->agreement_start_date,
            'next_due_date' => $agreement->agreement_end_date,
            'status' => 'pending',
            'admin_notes' => $request->additional_notes
        ]);

        // Log payment proof upload
        AuditLog::log('rent_payment_proof_uploaded', $payment);

        // Create notifications
        $this->notificationService->createInAppNotification(
            $user->id,
            'Payment Proof Uploaded',
            'Your rent payment proof has been uploaded successfully and is under review.',
            'success'
        );

        // Notify admin for verification
        $this->notificationService->createInAppNotification(
            1, // Admin user ID
            'New Payment Verification Required',
            "Rent payment proof uploaded for property '{$agreement->property->title}' - Amount: ₦" . number_format($request->amount),
            'info'
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $agreement->landlord_id,
            'Payment Proof Submitted',
            "Payment proof has been submitted for your property '{$agreement->property->title}'.",
            'info'
        );

        return ApiResponse::respond(
            data: [
                'payment' => $payment->load('rentalAgreement.property'),
                'verification_timeline' => '24-48 hours',
                'next_steps' => [
                    'wait_for_verification',
                    'receive_confirmation',
                    'agreement_activation'
                ]
            ],
            message: 'Payment proof uploaded successfully. Your payment will be verified within 24-48 hours.'
        );
    }

    /**
     * Get payment history for authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentHistory(Request $request)
    {
        $user = $request->user();

        $payments = RentPayment::with(['rentalAgreement.property', 'rentalAgreement.landlord:id,first_name,last_name'])
            ->where('user_id', $user->id)
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->property_id, function ($query, $propertyId) {
                return $query->whereHas('rentalAgreement', function ($q) use ($propertyId) {
                    $q->where('property_id', $propertyId);
                });
            })
            ->when($request->get('date_from'), function ($query, $dateFrom) {
                return $query->whereDate('payment_date', '>=', $dateFrom);
            })
            ->when($request->get('date_to'), function ($query, $dateTo) {
                return $query->whereDate('payment_date', '<=', $dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add computed properties
        $payments->getCollection()->transform(function ($payment) {
            $payment->is_overdue = $payment->isOverdue();
            $payment->days_overdue = $payment->days_overdue;
            $payment->formatted_amount = '₦' . number_format($payment->amount, 2);
            return $payment;
        });

        // Add summary statistics
        $summary = [
            'total_paid' => RentPayment::where('user_id', $user->id)
                ->where('status', 'verified')
                ->sum('amount'),
            'pending_payments' => RentPayment::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count(),
            'rejected_payments' => RentPayment::where('user_id', $user->id)
                ->where('status', 'rejected')
                ->count(),
            'overdue_payments' => RentPayment::where('user_id', $user->id)
                ->overdue()
                ->count()
        ];

        return ApiResponse::respond(
            data: [
                'payments' => $payments,
                'summary' => $summary
            ],
            message: 'Payment history retrieved successfully'
        );
    }

    /**
     * Get user's rented apartments
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyApartments(Request $request)
    {
        $user = $request->user();

        $apartments = RentalAgreement::with([
            'property.media',
            'property.landlord:id,first_name,last_name,phone_number,email',
            'property.agent:id,first_name,last_name,phone_number,email',
            'rentPayments' => function ($query) {
                $query->where('status', 'verified')->orderBy('payment_date', 'desc');
            }
        ])
            ->where('tenant_id', $user->id)
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('expiring_soon'), function ($query) {
                return $query->where('agreement_end_date', '<=', now()->addDays(30))
                    ->where('status', 'active');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add computed properties
        $apartments->getCollection()->transform(function ($agreement) {
            $agreement->days_until_expiry = $agreement->days_until_expiry;
            $agreement->total_paid = $agreement->total_paid;
            $agreement->outstanding_amount = $agreement->outstanding_amount;
            $agreement->last_payment = $agreement->last_payment;
            $agreement->next_payment_due = $agreement->next_payment_due;
            $agreement->is_expiring = $agreement->isExpiring(30);
            $agreement->monthly_payment = $agreement->monthly_payment;
            $agreement->occupancy_duration = $agreement->created_at->diffInMonths(now());
            return $agreement;
        });

        // Add summary statistics
        $summary = [
            'total_apartments' => RentalAgreement::where('tenant_id', $user->id)->count(),
            'active_apartments' => RentalAgreement::where('tenant_id', $user->id)
                ->where('status', 'active')->count(),
            'expiring_soon' => RentalAgreement::where('tenant_id', $user->id)
                ->where('status', 'active')
                ->where('agreement_end_date', '<=', now()->addDays(30))
                ->count(),
            'total_rent_paid' => RentPayment::where('user_id', $user->id)
                ->where('status', 'verified')
                ->sum('amount')
        ];

        return ApiResponse::respond(
            data: [
                'apartments' => $apartments,
                'summary' => $summary
            ],
            message: 'Apartments retrieved successfully'
        );
    }

    /**
     * Report an issue for rented apartment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportIssue(ReportIssueRequest $request)
    {

        $user = $request->user();
        $agreement = RentalAgreement::with(['property'])
            ->where('tenant_id', $user->id)
            ->findOrFail($request->rental_agreement_id);

        // Only allow issue reporting for active agreements
        if ($agreement->status !== 'active') {
            return ApiResponse::respond(
                message: 'Can only report issues for active rental agreements',
                status: false,
                statusCode: 400
            );
        }

        $attachmentUrls = [];

        // Upload attachments if provided
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $upload = $this->fileUploadService->uploadToCloudinary($attachment, 'support/attachments');
                if ($upload['success']) {
                    $attachmentUrls[] = [
                        'url' => $upload['url'],
                        'type' => $attachment->getClientMimeType(),
                        'size' => $attachment->getSize(),
                        'name' => $attachment->getClientOriginalName()
                    ];
                }
            }
        }

        // Create support ticket
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'property_id' => $agreement->property_id,
            'rental_agreement_id' => $agreement->id,
            'ticket_type' => 'property_issue',
            'subject' => $request->subject,
            'description' => $request->description .
                ($request->location_in_property ? "\n\nLocation: " . $request->location_in_property : ''),
            'priority' => $request->priority,
            'attachments' => $attachmentUrls,
            'status' => 'open'
        ]);

        // Log issue report
        AuditLog::log('property_issue_reported', $ticket);

        // Create notifications based on priority
        $notificationType = match ($request->priority) {
            'urgent' => 'error',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'info',
            default => 'info'
        };

        // Notify user
        $this->notificationService->createInAppNotification(
            $user->id,
            'Issue Reported',
            "Your {$request->priority} priority issue '{$ticket->subject}' has been submitted with ticket #{$ticket->ticket_number}.",
            'success'
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $agreement->landlord_id,
            'Property Issue Reported',
            "A {$request->priority} priority issue has been reported for your property '{$agreement->property->title}' - Ticket #{$ticket->ticket_number}",
            $notificationType
        );

        // Notify agent if assigned
        if ($agreement->agent_id) {
            $this->notificationService->createInAppNotification(
                $agreement->agent_id,
                'Property Issue Reported',
                "A {$request->priority} priority issue has been reported for property '{$agreement->property->title}' - Ticket #{$ticket->ticket_number}",
                $notificationType
            );
        }

        // For urgent issues, also notify admin immediately
        if ($request->priority === 'urgent') {
            $this->notificationService->createInAppNotification(
                1, // Admin user ID
                'URGENT: Property Issue',
                "URGENT issue reported for property '{$agreement->property->title}' - Immediate attention required",
                'error'
            );
        }

        return ApiResponse::respond(
            data: [
                'ticket' => $ticket,
                'estimated_response_time' => match ($request->priority) {
                    'urgent' => '2-4 hours',
                    'high' => '4-8 hours',
                    'medium' => '1-2 business days',
                    'low' => '2-3 business days',
                    default => '1-2 business days'
                }
            ],
            message: 'Issue report submitted successfully'
        );
    }

    /**
     * Get bank details for rent payments
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankDetails(Request $request)
    {
        $bankDetails = SystemSetting::get('shelterbaze_bank_details', [
            'account_number' => '1234567890',
            'bank_name' => 'First Bank of Nigeria',
            'account_name' => 'Shelterbaze Limited'
        ]);

        // Add additional payment information
        $paymentInfo = [
            'bank_details' => $bankDetails,
            'payment_instructions' => [
                'Make transfer to the account above',
                'Use your rental agreement ID as reference',
                'Upload payment proof immediately after transfer',
                'Payment verification takes 24-48 hours',
                'Contact support if payment is not verified within 48 hours'
            ],
            'supported_banks' => [
                'First Bank of Nigeria',
                'Wema Bank',
                'GTBank',
                'Access Bank',
                'Zenith Bank',
                'UBA',
                'Fidelity Bank',
                'All other commercial banks in Nigeria'
            ],
            'payment_channels' => [
                'Internet Banking',
                'Mobile Banking',
                'Bank Transfer',
                'ATM Transfer'
            ]
        ];

        return ApiResponse::respond(
            data: $paymentInfo,
            message: 'Bank details retrieved successfully'
        );
    }


    /**
     * Get rental agreement details
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRentalAgreement($id, Request $request)
    {
        $user = $request->user();

        $agreement = RentalAgreement::with([
            'property.media',
            'property.landlord:id,first_name,last_name,email,phone_number',
            'property.agent:id,first_name,last_name,email,phone_number',
            'rentPayments' => function ($query) {
                $query->orderBy('payment_date', 'desc');
            }
        ])
            ->where('tenant_id', $user->id)
            ->findOrFail($id);

        // Add computed properties
        $agreement->days_until_expiry = $agreement->days_until_expiry;
        $agreement->total_paid = $agreement->total_paid;
        $agreement->outstanding_amount = $agreement->outstanding_amount;
        $agreement->last_payment = $agreement->last_payment;
        $agreement->next_payment_due = $agreement->next_payment_due;
        $agreement->is_expiring = $agreement->isExpiring(30);
        $agreement->monthly_payment = $agreement->monthly_payment;
        $agreement->occupancy_duration = $agreement->created_at->diffInMonths(now());
        $agreement->lease_progress = $this->calculateLeaseProgress($agreement);

        // Payment summary
        $paymentSummary = [
            'total_payments_made' => $agreement->rentPayments()->verified()->count(),
            'total_amount_paid' => $agreement->rentPayments()->verified()->sum('amount'),
            'pending_payments' => $agreement->rentPayments()->pending()->count(),
            'rejected_payments' => $agreement->rentPayments()->rejected()->count(),
            'average_payment_amount' => $agreement->rentPayments()->verified()->avg('amount') ?? 0
        ];

        return ApiResponse::respond(
            data: [
                'rental_agreement' => $agreement,
                'payment_summary' => $paymentSummary
            ],
            message: 'Rental agreement retrieved successfully'
        );
    }

    /**
     * Request lease renewal
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestRenewal(RenewalRequest $request)
    {
        $user = $request->user();
        $agreement = RentalAgreement::with(['property'])
            ->where('tenant_id', $user->id)
            ->findOrFail($request->rental_agreement_id);

        if ($agreement->status !== 'active') {
            return ApiResponse::respond(
                message: 'Can only request renewal for active rental agreements',
                status: false,
                statusCode: 400
            );
        }

        // Check if renewal request already exists
        $existingRenewal = RentalAgreement::where('property_id', $agreement->property_id)
            ->where('tenant_id', $user->id)
            ->where('status', 'pending')
            ->where('agreement_start_date', '>', $agreement->agreement_end_date)
            ->first();

        if ($existingRenewal) {
            return ApiResponse::respond(
                message: 'You already have a pending renewal request for this property',
                status: false,
                statusCode: 400
            );
        }

        // Check if renewal is requested too early (not within 90 days of expiry)
        if ($agreement->agreement_end_date->diffInDays(now()) > 90) {
            return ApiResponse::respond(
                message: 'Renewal can only be requested within 90 days of lease expiry',
                status: false,
                statusCode: 400
            );
        }

        $startDate = Carbon::parse($request->proposed_start_date);
        $endDate = $startDate->copy()->addMonths($request->renewal_period_months)->subDay();

        // Calculate amounts (use proposed rent or current property rate)
        $property = $agreement->property;
        $newRentAmount = $request->proposed_rent_amount ?? $property->rent_amount;
        $rentAmount = $newRentAmount * $request->renewal_period_months;
        $commission = ($newRentAmount * 0.10) * $request->renewal_period_months; // 10% commission
        $totalAmount = $rentAmount + $commission;

        // Apply renewal discount (5% discount for returning tenants)
        $renewalDiscount = $rentAmount * 0.05;
        $rentAmount -= $renewalDiscount;
        $totalAmount = $rentAmount + $commission;

        // Create renewal agreement
        $renewal = RentalAgreement::create([
            'property_id' => $property->id,
            'tenant_id' => $user->id,
            'landlord_id' => $property->landlord_id,
            'agent_id' => $property->agent_id,
            'rent_amount' => $rentAmount,
            'shelterbaze_commission' => $commission,
            'total_amount' => $totalAmount,
            'agreement_start_date' => $startDate,
            'agreement_end_date' => $endDate,
            'status' => 'pending',
            'terms_conditions' => $request->renewal_notes
        ]);

        // Log renewal request
        AuditLog::log('lease_renewal_requested', $renewal);

        // Create notifications
        $this->notificationService->createInAppNotification(
            $user->id,
            'Renewal Request Submitted',
            "Your lease renewal request for '{$property->title}' has been submitted with 5% returning tenant discount.",
            'success'
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $property->landlord_id,
            'Lease Renewal Request',
            "Your tenant has requested to renew their lease for '{$property->title}' for {$request->renewal_period_months} months.",
            'info'
        );

        // Notify agent if assigned
        if ($property->agent_id) {
            $this->notificationService->createInAppNotification(
                $property->agent_id,
                'Lease Renewal Request',
                "A lease renewal request has been submitted for property '{$property->title}'.",
                'info'
            );
        }

        return ApiResponse::respond(
            data: [
                'renewal_agreement' => $renewal->load('property'),
                'renewal_discount' => $renewalDiscount,
                'savings' => [
                    'returning_tenant_discount' => $renewalDiscount,
                    'original_amount' => $rentAmount + $renewalDiscount,
                    'discounted_amount' => $rentAmount,
                    'total_with_commission' => $totalAmount
                ]
            ],
            message: 'Lease renewal request submitted successfully'
        );
    }

    /**
     * Get payment receipt
     * 
     * @param int $paymentId
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentReceipt($paymentId, Request $request)
    {
        $user = $request->user();

        $payment = RentPayment::with([
            'rentalAgreement.property',
            'rentalAgreement.landlord:id,first_name,last_name',
            'user:id,first_name,last_name,email',
            'verifiedBy:id,first_name,last_name'
        ])->where('user_id', $user->id)
            ->where('status', 'verified')
            ->findOrFail($paymentId);

        $receipt = [
            'receipt_number' => 'SB-RCP-' . str_pad($payment->id, 8, '0', STR_PAD_LEFT),
            'transaction_reference' => "RENT_{$payment->rental_agreement_id}_{$payment->user_id}",
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'verification_date' => $payment->verified_at->format('Y-m-d H:i:s'),
            'amount_paid' => $payment->amount,
            'payment_method' => ucfirst($payment->payment_type) . ' Transfer',
            'property_details' => [
                'title' => $payment->rentalAgreement->property->title,
                'address' => $payment->rentalAgreement->property->full_address,
                'property_type' => str_replace('_', ' ', $payment->rentalAgreement->property->property_type)
            ],
            'tenant_details' => [
                'name' => $payment->user->full_name,
                'email' => $payment->user->email
            ],
            'landlord_details' => [
                'name' => $payment->rentalAgreement->landlord->full_name
            ],
            'rental_period' => [
                'start_date' => $payment->rentalAgreement->agreement_start_date->format('Y-m-d'),
                'end_date' => $payment->rentalAgreement->agreement_end_date->format('Y-m-d'),
                'duration_months' => $payment->rentalAgreement->agreement_start_date
                    ->diffInMonths($payment->rentalAgreement->agreement_end_date)
            ],
            'payment_breakdown' => [
                'rent_amount' => $payment->rentalAgreement->rent_amount,
                'shelterbaze_commission' => $payment->rentalAgreement->shelterbaze_commission,
                'total_amount' => $payment->rentalAgreement->total_amount,
                'amount_paid' => $payment->amount,
                'payment_status' => 'VERIFIED'
            ],
            'verification_details' => [
                'verified_by' => $payment->verifiedBy?->full_name ?? 'System Admin',
                'verification_date' => $payment->verified_at->format('Y-m-d H:i:s'),
                'admin_notes' => $payment->admin_notes
            ],
            'next_payment_due' => $payment->next_due_date?->format('Y-m-d'),
            'receipt_generated_at' => now()->format('Y-m-d H:i:s'),
            'company_details' => [
                'name' => 'Shelterbaze Limited',
                'address' => 'Lagos, Nigeria',
                'email' => 'support@shelterbaze.com',
                'phone' => '+234-800-SHELTER'
            ]
        ];

        return ApiResponse::respond(
            data: $receipt,
            message: 'Payment receipt retrieved successfully'
        );
    }


    /**
     * Get rent payment summary and analytics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentSummary(Request $request)
    {
        $user = $request->user();

        $summary = [
            'total_paid' => RentPayment::where('user_id', $user->id)
                ->where('status', 'verified')
                ->sum('amount'),
            'total_payments_made' => RentPayment::where('user_id', $user->id)
                ->where('status', 'verified')
                ->count(),
            'pending_payments' => RentPayment::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count(),
            'rejected_payments' => RentPayment::where('user_id', $user->id)
                ->where('status', 'rejected')
                ->count(),
            'active_leases' => RentalAgreement::where('tenant_id', $user->id)
                ->where('status', 'active')
                ->count(),
            'expired_leases' => RentalAgreement::where('tenant_id', $user->id)
                ->where('status', 'expired')
                ->count(),
            'upcoming_renewals' => RentalAgreement::where('tenant_id', $user->id)
                ->where('status', 'active')
                ->where('agreement_end_date', '>', now())
                ->where('agreement_end_date', '<=', now()->addMonths(3))
                ->count(),
            'average_rent_paid' => RentPayment::where('user_id', $user->id)
                ->where('status', 'verified')
                ->avg('amount') ?? 0
        ];

        // Recent payments (last 5)
        $recentPayments = RentPayment::with(['rentalAgreement.property:id,title'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Upcoming lease expiries (next 90 days)
        $upcomingExpiries = RentalAgreement::with(['property:id,title'])
            ->where('tenant_id', $user->id)
            ->where('status', 'active')
            ->where('agreement_end_date', '>', now())
            ->where('agreement_end_date', '<=', now()->addDays(90))
            ->orderBy('agreement_end_date', 'asc')
            ->get()
            ->map(function ($agreement) {
                return [
                    'agreement_id' => $agreement->id,
                    'property_title' => $agreement->property->title,
                    'expiry_date' => $agreement->agreement_end_date->format('Y-m-d'),
                    'days_until_expiry' => $agreement->days_until_expiry,
                    'can_renew' => $agreement->days_until_expiry <= 90,
                    'monthly_rent' => $agreement->rent_amount / 12 // Assuming yearly rent
                ];
            });

        // Monthly payment analytics (last 12 months)
        $monthlyPayments = RentPayment::selectRaw('MONTH(payment_date) as month, YEAR(payment_date) as year, SUM(amount) as total')
            ->where('user_id', $user->id)
            ->where('status', 'verified')
            ->where('payment_date', '>=', now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Property types rented
        $propertyTypes = RentalAgreement::join('properties', 'rental_agreements.property_id', '=', 'properties.id')
            ->where('rental_agreements.tenant_id', $user->id)
            ->selectRaw('properties.property_type, COUNT(*) as count')
            ->groupBy('properties.property_type')
            ->get();

        return ApiResponse::respond(
            data: [
                'summary' => $summary,
                'recent_payments' => $recentPayments,
                'upcoming_expiries' => $upcomingExpiries,
                'monthly_chart_data' => $monthlyPayments,
                'property_types_rented' => $propertyTypes
            ],
            message: 'Payment summary retrieved successfully'
        );
    }

    /**
     * Cancel rental agreement (before payment verification)
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRentalRequest($id, CancelRentalRequest $request)
    {
        $user = $request->user();

        $agreement = RentalAgreement::with(['property'])
            ->where('tenant_id', $user->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        // Check if there are any verified payments
        if ($agreement->rentPayments()->verified()->exists()) {
            return ApiResponse::respond(
                message: 'Cannot cancel rental agreement with verified payments',
                status: false,
                statusCode: 400
            );
        }

        // Cancel pending payments
        $agreement->rentPayments()->pending()->update([
            'status' => 'rejected',
            'rejection_reason' => 'Agreement cancelled by tenant'
        ]);

        // Update agreement status
        $agreement->update([
            'status' => 'terminated',//'cancelled',
            'terms_conditions' => ($agreement->terms_conditions ?? '') .
                "\n\nCancelled by tenant: " . $request->cancellation_reason
        ]);

        // Log cancellation
        AuditLog::log('rental_agreement_cancelled', $agreement, null, [
            'cancellation_reason' => $request->cancellation_reason
        ]);

        // Create notifications
        $this->notificationService->createInAppNotification(
            $user->id,
            'Rental Request Cancelled',
            "Your rental request for '{$agreement->property->title}' has been cancelled.",
            'warning'
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $agreement->landlord_id,
            'Rental Request Cancelled',
            "A tenant has cancelled their rental request for your property '{$agreement->property->title}'. Reason: {$request->cancellation_reason}",
            'info'
        );

        // Notify agent if assigned
        if ($agreement->agent_id) {
            $this->notificationService->createInAppNotification(
                $agreement->agent_id,
                'Rental Request Cancelled',
                "A rental request has been cancelled for property '{$agreement->property->title}'.",
                'info'
            );
        }

        return ApiResponse::respond(
            message: 'Rental request cancelled successfully'
        );
    }

    /**
     * Request early lease termination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEarlyTermination(EarlyTerminationRequest $request)
    {
        $user = $request->user();
        $agreement = RentalAgreement::with(['property'])
            ->where('tenant_id', $user->id)
            ->where('status', 'active')
            ->findOrFail($request->rental_agreement_id);

       $terminationDate = Carbon::parse($request->termination_date);
       
        // Check if termination date is reasonable (at least 30 days notice)
        if (now()->diffInDays($terminationDate, false) < 30) {
            return ApiResponse::respond(
                message: 'Early termination requires at least 30 days notice',
                status: false,
                statusCode: 400
            );
        }

        // Calculate penalty (typically 2 months rent or remaining rent amount)
        $monthsRemaining = $terminationDate->diffInMonths($agreement->agreement_end_date);
        $monthlyRent = $agreement->rent_amount / 12; // Assuming yearly rent
        $penaltyAmount = min($monthlyRent * 2, $monthlyRent * $monthsRemaining);

        // Create support ticket for termination request
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'property_id' => $agreement->property_id,
            'rental_agreement_id' => $agreement->id,
            'ticket_type' => 'account_issue',
            'subject' => 'Early Lease Termination Request',
            'description' => "Tenant requesting early lease termination.\n\n" .
                "Termination Date: {$terminationDate->format('Y-m-d')}\n" .
                "Reason: {$request->termination_reason}\n" .
                "Willing to pay penalty: " . ($request->willing_to_pay_penalty ? 'Yes' : 'No') . "\n" .
                "Calculated penalty: ₦" . number_format($penaltyAmount),
            'priority' => 'medium',
            'status' => 'open'
        ]);

        // Log termination request
        AuditLog::log('early_termination_requested', $agreement, null, [
            'termination_date' => $terminationDate->format('Y-m-d'),
            'reason' => $request->termination_reason,
            'penalty_amount' => $penaltyAmount,
            'ticket_id' => $ticket->id
        ]);

        // Create notifications
        $this->notificationService->createInAppNotification(
            $user->id,
            'Termination Request Submitted',
            "Your early lease termination request has been submitted and will be reviewed.",
            'info'
        );

        // Notify landlord
        $this->notificationService->createInAppNotification(
            $agreement->landlord_id,
            'Early Termination Request',
            "Your tenant has requested early lease termination for '{$agreement->property->title}'. Review required.",
            'warning'
        );

        return ApiResponse::respond(
            message: 'Early termination request submitted successfully',
            data: [
                'ticket' => $ticket,
                'penalty_details' => [
                    'months_remaining' => $monthsRemaining,
                    'monthly_rent' => $monthlyRent,
                    'calculated_penalty' => $penaltyAmount,
                    'penalty_description' => 'Lesser of 2 months rent or remaining rent amount'
                ],
                'next_steps' => [
                    'Landlord will review your request',
                    'Negotiation may be required',
                    'Final terms will be communicated via support ticket',
                    'Upon agreement, termination will be processed'
                ]
            ]
        );
    }

    /**
     * Get rental insights and recommendations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRentalInsights(Request $request)
    {
        $user = $request->user();
        $insights = [];

        // Check for expiring leases
        $expiringLeases = RentalAgreement::where('tenant_id', $user->id)
            ->where('status', 'active')
            ->where('agreement_end_date', '<=', now()->addDays(60))
            ->count();

        if ($expiringLeases > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Leases Expiring Soon',
                'message' => "You have {$expiringLeases} lease(s) expiring within 60 days. Consider renewal to avoid disruption.",
                'action' => 'review_renewals',
                'priority' => 'high'
            ];
        }

        // Check payment patterns
        $latePayments = RentPayment::where('user_id', $user->id)
            ->where('status', 'verified')
            ->where('verified_at', '>', 'due_date')
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        if ($latePayments > 2) {
            $insights[] = [
                'type' => 'suggestion',
                'title' => 'Payment Optimization',
                'message' => 'You have made several late payments recently. Consider setting up payment reminders.',
                'action' => 'setup_reminders',
                'priority' => 'medium'
            ];
        }

        // Check for rental savings opportunities
        $totalRentPaid = RentPayment::where('user_id', $user->id)
            ->where('status', 'verified')
            ->where('created_at', '>=', now()->subYear())
            ->sum('amount');

        if ($totalRentPaid > 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Annual Rent Summary',
                'message' => "You've paid ₦" . number_format($totalRentPaid) . " in rent this year. Consider our savings plan for future rentals.",
                'action' => 'explore_savings',
                'priority' => 'low'
            ];
        }

        // Achievement recognition
        $onTimePayments = RentPayment::where('user_id', $user->id)
            ->where('status', 'verified')
            ->where('verified_at', '<=', 'due_date')
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        if ($onTimePayments >= 6) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Excellent Payment Record',
                'message' => 'You have an excellent payment history! You qualify for our premium tenant benefits.',
                'action' => 'explore_benefits',
                'priority' => 'low'
            ];
        }

        return ApiResponse::respond(
            data: $insights,
            message: 'Rental insights retrieved successfully'
        );
    }

    /**
     * Export rental data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportRentalData(Request $request)
    {
        $user = $request->user();

        $agreements = RentalAgreement::with(['property', 'rentPayments'])
            ->where('tenant_id', $user->id)
            ->get();

        $exportData = [
            'user_info' => [
                'name' => $user->full_name,
                'email' => $user->email,
                'export_date' => now()->format('Y-m-d H:i:s')
            ],
            'summary' => [
                'total_agreements' => $agreements->count(),
                'active_agreements' => $agreements->where('status', 'active')->count(),
                'total_rent_paid' => $agreements->sum(function ($agreement) {
                    return $agreement->rentPayments()->verified()->sum('amount');
                }),
                'properties_rented' => $agreements->count()
            ],
            'rental_agreements' => $agreements->map(function ($agreement) {
                return [
                    'property_title' => $agreement->property->title,
                    'property_address' => $agreement->property->full_address,
                    'rent_amount' => $agreement->rent_amount,
                    'commission' => $agreement->shelterbaze_commission,
                    'total_amount' => $agreement->total_amount,
                    'start_date' => $agreement->agreement_start_date->format('Y-m-d'),
                    'end_date' => $agreement->agreement_end_date->format('Y-m-d'),
                    'status' => $agreement->status,
                    'payments_made' => $agreement->rentPayments()->verified()->count(),
                    'total_paid' => $agreement->rentPayments()->verified()->sum('amount'),
                    'occupancy_duration' => $agreement->created_at->diffInDays($agreement->agreement_end_date)
                ];
            }),
            'payment_history' => RentPayment::with(['rentalAgreement.property'])
                ->where('user_id', $user->id)
                ->orderBy('payment_date', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'payment_date' => $payment->payment_date->format('Y-m-d'),
                        'property_title' => $payment->rentalAgreement->property->title,
                        'amount' => $payment->amount,
                        'payment_type' => $payment->payment_type,
                        'status' => $payment->status,
                        'verified_date' => $payment->verified_at?->format('Y-m-d H:i:s')
                    ];
                })
        ];

        return ApiResponse::respond(
            data: $exportData,
            message: 'Rental data exported successfully'
        );
    }

    /**
     * Calculate lease progress percentage
     * 
     * @param RentalAgreement $agreement
     * @return float
     */
    private function calculateLeaseProgress($agreement)
    {
        $totalDays = $agreement->agreement_start_date->diffInDays($agreement->agreement_end_date);
        $daysPassed = $agreement->agreement_start_date->diffInDays(now());

        if ($totalDays <= 0) {
            return 100;
        }

        return min(100, ($daysPassed / $totalDays) * 100);
    }
}
