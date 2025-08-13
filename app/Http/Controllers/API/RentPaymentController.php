<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\RentalAgreement;
use App\Models\RentPayment;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RentPaymentController extends Controller
{
    private $fileUploadService;
    private $notificationService;

    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
    }

    /**
     * Generate invoice for rent payment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'rental_period_months' => 'required|integer|min:1|max:24',
            'start_date' => 'required|date|after_or_equal:today',
            'lease_terms' => 'nullable|string|max:1000'
        ], [
            'rental_period_months.min' => 'Rental period must be at least 1 month',
            'rental_period_months.max' => 'Rental period cannot exceed 24 months',
            'start_date.after_or_equal' => 'Start date must be today or in the future'
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
            $property = Property::with(['landlord', 'agent'])->findOrFail($request->property_id);

            // Check if property is available
            if (!$property->isAvailable()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Property is not available for rent'
                ], 400);
            }

            // Check if user has paid engagement fee
            if (!$property->hasUserPaidEngagementFee($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please pay engagement fee first to proceed with rental'
                ], 402);
            }

            // Check if user already has active agreement for this property
            $existingAgreement = RentalAgreement::where('property_id', $property->id)
                ->where('tenant_id', $user->id)
                ->whereIn('status', ['active', 'pending'])
                ->first();

            if ($existingAgreement) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active or pending rental agreement for this property'
                ], 400);
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

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'data' => [
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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice generation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload payment proof for rent
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPaymentProof(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'payment_proof' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_type' => 'required|in:online,offline',
            'amount' => 'required|numeric|min:1000',
            'payment_reference' => 'nullable|string|max:100',
            'additional_notes' => 'nullable|string|max:500'
        ], [
            'payment_proof.max' => 'Payment proof file cannot exceed 5MB',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future',
            'amount.min' => 'Payment amount must be at least ₦1,000'
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
            $agreement = RentalAgreement::with(['property'])
                ->where('tenant_id', $user->id)
                ->findOrFail($request->rental_agreement_id);

            // Check if agreement is in valid state for payment
            if (!in_array($agreement->status, ['pending', 'active'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot upload payment proof for this agreement status'
                ], 400);
            }

            // Check if there's already a pending payment for this agreement
            $pendingPayment = RentPayment::where('rental_agreement_id', $agreement->id)
                ->where('status', 'pending')
                ->first();

            if ($pendingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'There is already a pending payment verification for this agreement'
                ], 400);
            }

            // Validate payment amount against agreement
            $expectedAmount = $agreement->total_amount;
            $tolerance = 0.02; // 2% tolerance for bank charges
            $minAcceptable = $expectedAmount * (1 - $tolerance);
            $maxAcceptable = $expectedAmount * (1 + $tolerance);

            if ($request->amount < $minAcceptable || $request->amount > $maxAcceptable) {
                return response()->json([
                    'success' => false,
                    'message' => "Payment amount should be between ₦" . number_format($minAcceptable) . " and ₦" . number_format($maxAcceptable) . " (expected: ₦" . number_format($expectedAmount) . ")"
                ], 400);
            }

            // Upload payment proof
            $proofUpload = $this->fileUploadService->uploadToCloudinary(
                $request->file('payment_proof'),
                'rent_payments/proofs'
            );

            if (!$proofUpload['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload payment proof',
                    'error' => $proofUpload['error']
                ], 500);
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

            return response()->json([
                'success' => true,
                'message' => 'Payment proof uploaded successfully. Your payment will be verified within 24-48 hours.',
                'data' => [
                    'payment' => $payment->load('rentalAgreement.property'),
                    'verification_timeline' => '24-48 hours',
                    'next_steps' => [
                        'wait_for_verification',
                        'receive_confirmation',
                        'agreement_activation'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment proof upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history for authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentHistory(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Payment history retrieved successfully',
                'data' => [
                    'payments' => $payments,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's rented apartments
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMyApartments(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Apartments retrieved successfully',
                'data' => [
                    'apartments' => $apartments,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch apartments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Report an issue for rented apartment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reportIssue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'issue_type' => 'required|in:maintenance,noise,security,utilities,plumbing,electrical,structural,cleaning,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'priority' => 'required|in:low,medium,high,urgent',
            'location_in_property' => 'nullable|string|max:100',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,png,jpg,pdf,mp4,mov|max:10240' // 10MB max
        ], [
            'attachments.*.max' => 'Each attachment cannot exceed 10MB',
            'attachments.max' => 'Maximum 5 attachments allowed'
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
            $agreement = RentalAgreement::with(['property'])
                ->where('tenant_id', $user->id)
                ->findOrFail($request->rental_agreement_id);

            // Only allow issue reporting for active agreements
            if ($agreement->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only report issues for active rental agreements'
                ], 400);
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
            $ticket = \App\Models\SupportTicket::create([
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
            $notificationType = match($request->priority) {
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

            return response()->json([
                'success' => true,
                'message' => 'Issue report submitted successfully',
                'data' => [
                    'ticket' => $ticket,
                    'estimated_response_time' => match($request->priority) {
                        'urgent' => '2-4 hours',
                        'high' => '4-8 hours',
                        'medium' => '1-2 business days',
                        'low' => '2-3 business days',
                        default => '1-2 business days'
                    }
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Issue report submission failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank details for rent payments
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankDetails(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Bank details retrieved successfully',
                'data' => $paymentInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank details',
                'error' => $e->getMessage()
            ], 500);
        }
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
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Rental agreement retrieved successfully',
                'data' => [
                    'rental_agreement' => $agreement,
                    'payment_summary' => $paymentSummary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rental agreement not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Request lease renewal
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestRenewal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'renewal_period_months' => 'required|integer|min:1|max:24',
            'proposed_start_date' => 'required|date|after_or_equal:today',
            'renewal_notes' => 'nullable|string|max:500',
            'proposed_rent_amount' => 'nullable|numeric|min:1000'
        ], [
            'renewal_period_months.min' => 'Renewal period must be at least 1 month',
            'renewal_period_months.max' => 'Renewal period cannot exceed 24 months',
            'proposed_start_date.after_or_equal' => 'Start date must be today or in the future',
            'proposed_rent_amount.min' => 'Proposed rent must be at least ₦1,000'
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
            $agreement = RentalAgreement::with(['property'])
                ->where('tenant_id', $user->id)
                ->findOrFail($request->rental_agreement_id);

            if ($agreement->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only request renewal for active agreements'
                ], 400);
            }

            // Check if renewal request already exists
            $existingRenewal = RentalAgreement::where('property_id', $agreement->property_id)
                ->where('tenant_id', $user->id)
                ->where('status', 'pending')
                ->where('agreement_start_date', '>', $agreement->agreement_end_date)
                ->first();

            if ($existingRenewal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Renewal request already exists for this property'
                ], 400);
            }

            // Check if renewal is requested too early (not within 90 days of expiry)
            if ($agreement->agreement_end_date->diffInDays(now()) > 90) {
                return response()->json([
                    'success' => false,
                    'message' => 'Renewal can only be requested within 90 days of lease expiry'
                ], 400);
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

            return response()->json([
                'success' => true,
                'message' => 'Lease renewal request submitted successfully',
                'data' => [
                    'renewal_agreement' => $renewal->load('property'),
                    'renewal_discount' => $renewalDiscount,
                    'savings' => [
                        'returning_tenant_discount' => $renewalDiscount,
                        'original_amount' => $rentAmount + $renewalDiscount,
                        'discounted_amount' => $rentAmount,
                        'total_with_commission' => $totalAmount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lease renewal request failed',
                'error' => $e->getMessage()
            ], 500);
        }
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
        try {
            $user = $request->user();

            $payment = RentPayment::with([
                'rentalAgreement.property',
                'rentalAgreement.landlord:id,first_name,last_name',
                'user:id,first_name,last_name,email',
                'verifiedBy:id,first_name,last_name'
            ])
                ->where('user_id', $user->id)
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

            return response()->json([
                'success' => true,
                'message' => 'Payment receipt retrieved successfully',
                'data' => ['receipt' => $receipt]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment receipt not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get rent payment summary and analytics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentSummary(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Payment summary retrieved successfully',
                'data' => [
                    'summary' => $summary,
                    'recent_payments' => $recentPayments,
                    'upcoming_expiries' => $upcomingExpiries,
                    'monthly_chart_data' => $monthlyPayments,
                    'property_types_rented' => $propertyTypes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel rental agreement (before payment verification)
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelRentalRequest($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|max:500'
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
            
            $agreement = RentalAgreement::with(['property'])
                ->where('tenant_id', $user->id)
                ->where('status', 'pending')
                ->findOrFail($id);

            // Check if there are any verified payments
            if ($agreement->rentPayments()->verified()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel rental agreement with verified payments'
                ], 400);
            }

            // Cancel pending payments
            $agreement->rentPayments()->pending()->update([
                'status' => 'rejected',
                'rejection_reason' => 'Agreement cancelled by tenant'
            ]);

            // Update agreement status
            $agreement->update([
                'status' => 'cancelled',
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

            return response()->json([
                'success' => true,
                'message' => 'Rental request cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel rental request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request early lease termination
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestEarlyTermination(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rental_agreement_id' => 'required|exists:rental_agreements,id',
            'termination_date' => 'required|date|after:today',
            'termination_reason' => 'required|string|max:1000',
            'willing_to_pay_penalty' => 'required|boolean'
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
            $agreement = RentalAgreement::with(['property'])
                ->where('tenant_id', $user->id)
                ->where('status', 'active')
                ->findOrFail($request->rental_agreement_id);

            $terminationDate = Carbon::parse($request->termination_date);
            
            // Check if termination date is reasonable (at least 30 days notice)
            if ($terminationDate->diffInDays(now()) < 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Early termination requires at least 30 days notice'
                ], 400);
            }

            // Calculate penalty (typically 2 months rent or remaining rent amount)
            $monthsRemaining = $terminationDate->diffInMonths($agreement->agreement_end_date);
            $monthlyRent = $agreement->rent_amount / 12; // Assuming yearly rent
            $penaltyAmount = min($monthlyRent * 2, $monthlyRent * $monthsRemaining);

            // Create support ticket for termination request
            $ticket = \App\Models\SupportTicket::create([
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

            return response()->json([
                'success' => true,
                'message' => 'Early termination request submitted successfully',
                'data' => [
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
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Early termination request failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rental insights and recommendations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRentalInsights(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Rental insights retrieved successfully',
                'data' => ['insights' => $insights]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental insights',
                'error' => $e->getMessage()
            ], 500);
        }
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

    /**
     * Export rental data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportRentalData(Request $request)
    {
        try {
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

            return response()->json([
                'success' => true,
                'message' => 'Rental data exported successfully',
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export rental data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}