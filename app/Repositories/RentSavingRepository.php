<?php

namespace App\Repositories;

use App\Http\Requests\RentSaving\CreateRentSavingRequest;
use App\Http\Requests\RentSaving\DepositSavingRequest;
use App\Http\Requests\RentSaving\PausePlanRequest;
use App\Http\Requests\RentSaving\UpdateRentSavingRequest;
use App\Http\Requests\RentSaving\VerifyDepositRequest;
use App\Http\Requests\RentSaving\WithdrawSavingRequest;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\RentSaving;
use App\Models\SavingsTransaction;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Util\ApiResponse;
use Illuminate\Http\Request;

class RentSavingRepository
{
    public function __construct(
        private PaymentService $paymentService,
        private NotificationService $notificationService
    ) {}

    /**
     * Display a listing of user's savings plans
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $savings = RentSaving::with(['property:id,title', 'transactions'])
            ->where('user_id', $user->id)
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('search'), function ($query, $search) {
                return $query->where('plan_name', 'LIKE', "%{$search}%");
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add computed properties
        $savings->getCollection()->transform(function ($saving) {
            $saving->progress_percentage = $saving->progress_percentage;
            $saving->remaining_amount = $saving->remaining_amount;
            $saving->days_until_due = $saving->days_until_due;
            $saving->can_withdraw = $saving->canWithdraw();
            $saving->total_deposits = $saving->deposits()->completed()->sum('net_amount');
            $saving->total_withdrawals = $saving->withdrawals()->completed()->sum('net_amount');
            return $saving;
        });

        return ApiResponse::respond(
            data: $savings, 
            message: 'Savings plans fetched successfully'
        );
    }

    public function create(CreateRentSavingRequest $request)
    {
        $user = $request->user();

        // Check savings plan limit per user (max 10 active plans)
        $activePlansCount = $user->rentSavings()->active()->count();
        if ($activePlansCount >= 10) {
            return ApiResponse::respond(
                message: 'You can have maximum 10 active savings plans',
                statusCode: 400,
                status: false
            );
        }

        // Check if property exists and is available (for internal properties)
        if ($request->property_id) {
            $property = Property::findOrFail($request->property_id);
            if (!$property->isAvailable()) {
                return ApiResponse::respond(
                    message: 'Selected property is not available',
                    statusCode: 400,
                    status: false
                );
            }

            // Check if user already has a savings plan for this property
            $existingPlan = RentSaving::where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->where('status', 'active')
                ->first();

            if ($existingPlan) {
                return ApiResponse::respond(
                    message: 'You already have an active savings plan for this property',
                    status: false,
                    statusCode: 400
                );
            }
        }

        $saving = RentSaving::create([
            'user_id' => $user->id,
            'property_id' => $request->property_id,
            'plan_name' => $request->plan_name,
            'target_amount' => $request->target_amount,
            'due_date' => $request->due_date,
            'is_external_property' => $request->is_external_property,
            'external_property_details' => $request->external_property_details,
        ]);

        // Log savings plan creation
        AuditLog::log('savings_plan_created', $saving);

        // Create notification
        $this->notificationService->createInAppNotification(
            $user->id,
            'Savings Plan Created',
            "Your savings plan '{$saving->plan_name}' has been created successfully with target of ₦" . number_format($saving->target_amount),
            'success'
        );

        return ApiResponse::respond(
            message: 'Savings plan created successfully',
            statusCode: 201,
            data: $saving->load('property')
        );
    }

    public function find(Request $request, $id)
    {
        $user = $request->user();

        $saving = RentSaving::with(['property', 'transactions' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])->where('user_id', $user->id)
            ->findOrFail($id);

        // Add computed properties
        $saving->progress_percentage = $saving->progress_percentage;
        $saving->remaining_amount = $saving->remaining_amount;
        $saving->days_until_due = $saving->days_until_due;
        $saving->can_withdraw = $saving->canWithdraw();
        $saving->early_withdrawal_penalty_amount = $saving->calculateEarlyWithdrawalPenalty();
        $saving->total_deposits = $saving->deposits()->completed()->sum('net_amount');
        $saving->total_withdrawals = $saving->withdrawals()->completed()->sum('net_amount');
        $saving->total_charges_paid = $saving->transactions()->sum('charge_amount');
        $saving->total_penalties_paid = $saving->transactions()->sum('penalty_amount');

        // Recent transactions (last 5)
        $saving->recent_transactions = $saving->transactions()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return ApiResponse::respond(
            message: 'Savings plan retrieved successfully',
            data: $saving
        );
    }

    public function update(UpdateRentSavingRequest $request, $id)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($id);

        if (!$saving->isActive()) {
            return ApiResponse::respond(
                status: false,
                statusCode: 400,
                message: 'Can only update active savings plans'
            );
        }

        // Don't allow reducing target amount below current amount
        if ($request->has('target_amount') && $request->target_amount < $saving->current_amount) {
            return ApiResponse::respond(
                status: false,
                statusCode: 400,
                message: 'Target amount cannot be less than current savings amount of ₦' . number_format($saving->current_amount)
            );
        }

        $oldData = $saving->toArray();
        $saving->update($request->only(['plan_name', 'target_amount', 'due_date', 'external_property_details']));

        // Log savings plan update
        AuditLog::log('savings_plan_updated', $saving, $oldData, $saving->fresh()->toArray());

        // Create notification
        $this->notificationService->createInAppNotification(
            $user->id,
            'Savings Plan Updated',
            "Your savings plan '{$saving->plan_name}' has been updated successfully.",
            'success'
        );

        return ApiResponse::respond(
            data: $saving->fresh(),
            message: 'Savings plan updated successfully'
        );
    }


    public function depositSaving(DepositSavingRequest $request)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($request->savings_id);

        if (!$saving->isActive()) {
            return ApiResponse::respond(
                status: false,
                statusCode: 400,
                message: 'Cannot deposit to inactive savings plan'
            );
        }

        $amount = $request->amount;

        // Check if deposit would exceed target amount
        $newTotalAmount = $saving->current_amount + $amount;
        if ($newTotalAmount > $saving->target_amount) {
            return ApiResponse::respond(
                status: false,
                statusCode: 400,
                message: "Deposit amount would exceed target. Maximum you can deposit is ₦" . number_format($saving->remaining_amount)
            );
        }

        // Check for pending deposits
        $pendingDeposit = $saving->transactions()
            ->where('transaction_type', 'deposit')
            ->where('status', 'pending')
            ->first();

        if ($pendingDeposit) {
            return ApiResponse::respond(
                status: false,
                statusCode: 400,
                message: 'You have a pending deposit transaction. Please complete or cancel it first.'
            );
        }

        $chargeAmount = $saving->calculateDepositCharge($amount);
        $netAmount = $amount - $chargeAmount;

        // Generate payment reference
        $reference = $this->paymentService->generatePaymentReference('SAV');

        // Create savings transaction
        $transaction = SavingsTransaction::create([
            'savings_id' => $saving->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'charge_amount' => $chargeAmount,
            'net_amount' => $netAmount,
            'transaction_type' => 'deposit',
            'payment_reference' => $reference,
            'status' => 'pending',
            'payment_method' => 'paystack'
        ]);

        // Initialize Paystack payment
        $this->paymentService->setCallbackUrl(route('rent_saving.verify', ['reference' => $reference]));

        $paymentData = $this->paymentService->initializePayment(
            $amount,
            $user->email,
            $reference,
            [
                'user_id' => $user->id,
                'savings_id' => $saving->id,
                'transaction_id' => $transaction->id,
                'plan_name' => $saving->plan_name,
                'transaction_type' => 'savings_deposit'
            ]
        );

        // Log deposit initiation
        AuditLog::log('savings_deposit_initiated', $transaction);

        return ApiResponse::respond(
            message: 'Deposit payment initialized successfully',
            data: [
                'payment_url' =>  $paymentData['data']['authorization_url'] ?? '',
                'reference' => $reference,
                'amount' => $amount,
                'charge_amount' => $chargeAmount,
                'net_amount' => $netAmount,
                'transaction_id' => $transaction->id
            ]
        );
    }

    public function verifyDeposit(VerifyDepositRequest $request)
    {
        $transaction = SavingsTransaction::where('payment_reference', $request->reference)->first();

        // Verify user owns this transaction
        if ($transaction->user_id !== $request->user()->id) {
            return ApiResponse::respond(
                message: 'Unauthorized transaction access',
                status: false,
                statusCode: 403
            );
        }

        if ($transaction->isCompleted()) {
            return ApiResponse::respond(
                message: 'Deposit already verified',
                data: $transaction->load('savings')
            );
        }

        if ($transaction->isFailed()) {
            return ApiResponse::respond(
                message: 'This transaction has already failed',
                status: false,
                statusCode: 400
            );
        }

        // Verify payment with Paystack
        $paymentDetails = $this->paymentService->verifyPayment($request->reference);

        if ($paymentDetails['status'] && $paymentDetails['data']['status'] === 'success') {
            // Update transaction
            $transaction->update([
                'status' => 'completed',
                'payment_data' => $paymentDetails['data']
            ]);

            // Update savings amount
            $transaction->savings->addAmount($transaction->net_amount);

            // Log successful deposit
            AuditLog::log('savings_deposit_completed', $transaction);

            // Create notification
            $this->notificationService->createInAppNotification(
                $transaction->user_id,
                'Deposit Successful',
                "Your deposit of ₦" . number_format($transaction->net_amount) . " to '{$transaction->savings->plan_name}' was successful.",
                'success'
            );

            return ApiResponse::respond(
                message: 'Deposit verified successfully',
                data: $transaction->load('savings')
            );
        } else {
            $transaction->markAsFailed();

            return ApiResponse::respond(
                message: 'Deposit verification failed. Payment was not successful.',
                status: false,
                statusCode: 400
            );
        }
    }

    public function withdrawSaving(WithdrawSavingRequest $request)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($request->savings_id);

        if (!$saving->isActive() && !$saving->isCompleted()) {
            return ApiResponse::respond(
                message: 'Can only withdraw from active or completed savings plans',
                status: false,
                statusCode: 400
            );
        }

        $amount = $request->amount;

        if ($amount > $saving->current_amount) {
            return ApiResponse::respond(
                message: 'Insufficient savings balance. Available balance: ₦' . number_format($saving->current_amount),
                status: false,
                statusCode: 400
            );
        }

        // Check for pending withdrawals
        $pendingWithdrawal = $saving->transactions()
            ->where('transaction_type', 'withdrawal')
            ->where('status', 'pending')
            ->first();

        if ($pendingWithdrawal) {
            return ApiResponse::respond(
                message: 'You have a pending withdrawal request. Please wait for it to be processed.',
                status: false,
                statusCode: 400
            );
        }

        $isEarlyWithdrawal = !$saving->canWithdraw();
        $penaltyAmount = $isEarlyWithdrawal ? $saving->calculateEarlyWithdrawalPenalty() : 0;
        $netAmount = $amount - $penaltyAmount;

        // Ensure net amount is positive
        if ($netAmount <= 0) {
            return ApiResponse::respond(
                message: 'Withdrawal amount is too low after penalty deduction',
                status: false,
                statusCode: 400
            );
        }

        // Create withdrawal transaction
        $transaction = SavingsTransaction::create([
            'savings_id' => $saving->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'penalty_amount' => $penaltyAmount,
            'net_amount' => $netAmount,
            'transaction_type' => 'withdrawal',
            'is_early_withdrawal' => $isEarlyWithdrawal,
            'status' => 'pending',
            'notes' => $request->withdrawal_reason
        ]);

        // Log withdrawal request
        AuditLog::log('savings_withdrawal_requested', $transaction);

        // Create notification
        $message = $isEarlyWithdrawal
            ? "Your early withdrawal request of ₦" . number_format($amount) . " (with ₦" . number_format($penaltyAmount) . " penalty) is being processed. You will receive ₦" . number_format($netAmount) . "."
            : "Your withdrawal request of ₦" . number_format($amount) . " is being processed.";

        $this->notificationService->createInAppNotification(
            $user->id,
            'Withdrawal Request Submitted',
            $message,
            'info'
        );

        // Notify admin about withdrawal request
        $this->notificationService->createInAppNotification(
            1, // Admin user ID
            'New Withdrawal Request',
            "User {$user->full_name} has requested withdrawal of ₦" . number_format($amount) . " from savings plan '{$saving->plan_name}'",
            'info'
        );

        return ApiResponse::respond(
            message: 'Withdrawal request submitted successfully. It will be processed within 3 days.',
            data: [
                'transaction' => $transaction,
                'is_early_withdrawal' => $isEarlyWithdrawal,
                'penalty_amount' => $penaltyAmount,
                'net_amount' => $netAmount,
                'processing_time' => '3 business days'
            ]
        );
    }


    public function getTransactionHistory(Request $request, $savingsId)
    {
        $user = $request->user();

        // Verify user owns the savings plan
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($savingsId);

        $transactions = SavingsTransaction::where('savings_id', $saving->id)
            ->when($request->transaction_type, function ($query, $type) {
                return $query->where('transaction_type', $type);
            })
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->get('date_from'), function ($query, $dateFrom) {
                return $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($request->get('date_to'), function ($query, $dateTo) {
                return $query->whereDate('created_at', '<=', $dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add summary statistics
        $summary = [
            'total_deposits' => SavingsTransaction::where('savings_id', $saving->id)
                ->deposits()->completed()->sum('net_amount'),
            'total_withdrawals' => SavingsTransaction::where('savings_id', $saving->id)
                ->withdrawals()->completed()->sum('net_amount'),
            'total_charges' => SavingsTransaction::where('savings_id', $saving->id)
                ->sum('charge_amount'),
            'total_penalties' => SavingsTransaction::where('savings_id', $saving->id)
                ->sum('penalty_amount'),
            'transaction_count' => SavingsTransaction::where('savings_id', $saving->id)->count()
        ];

        return ApiResponse::respond(
            message: 'Transaction history retrieved successfully',
            data: [
                'transactions' => $transactions,
                'summary' => $summary,
                'savings_plan' => $saving->only(['id', 'plan_name', 'current_amount', 'target_amount'])
            ]
        );
    }


    public function dashboard(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_savings' => $user->rentSavings()->sum('current_amount'),
            'active_plans' => $user->rentSavings()->active()->count(),
            'completed_plans' => $user->rentSavings()->completed()->count(),
            'cancelled_plans' => $user->rentSavings()->where('status', 'cancelled')->count(),
            'total_deposits' => $user->savingsTransactions()->deposits()->completed()->sum('amount'),
            'total_withdrawals' => $user->savingsTransactions()->withdrawals()->completed()->sum('amount'),
            'total_charges_paid' => $user->savingsTransactions()->sum('charge_amount'),
            'total_penalties_paid' => $user->savingsTransactions()->sum('penalty_amount'),
            'pending_transactions' => $user->savingsTransactions()->pending()->count()
        ];

        // Recent transactions (last 10)
        $recentTransactions = SavingsTransaction::with('savings:id,plan_name')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Due soon savings plans (due within 30 days)
        $dueSoon = $user->rentSavings()
            ->active()
            ->dueSoon(30)
            ->with('property:id,title')
            ->get()
            ->map(function ($saving) {
                return [
                    'id' => $saving->id,
                    'plan_name' => $saving->plan_name,
                    'target_amount' => $saving->target_amount,
                    'current_amount' => $saving->current_amount,
                    'progress_percentage' => $saving->progress_percentage,
                    'due_date' => $saving->due_date,
                    'days_until_due' => $saving->days_until_due,
                    'property_title' => $saving->property?->title
                ];
            });

        // Monthly savings chart data (last 12 months)
        $monthlySavings = SavingsTransaction::selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(net_amount) as total')
            ->where('user_id', $user->id)
            ->where('transaction_type', 'deposit')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return ApiResponse::respond(
            message: 'Savings dashboard retrieved successfully',
            data: [
                'stats' => $stats,
                'recent_transactions' => $recentTransactions,
                'due_soon' => $dueSoon,
                'monthly_chart_data' => $monthlySavings
            ]
        );
    }

    public function cancelPlan(Request $request, $id)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($id);

        if (!$saving->isActive()) {
            return ApiResponse::respond(
                message: 'Can only cancel active savings plans',
                status: false,
                statusCode: 400
            );
        }

        if ($saving->current_amount > 0) {
            return ApiResponse::respond(
                message: 'Cannot cancel savings plan with existing balance of ₦' . number_format($saving->current_amount) . '. Please withdraw funds first.',
                status: false,
                statusCode: 400
            );
        }

        // Check for pending transactions
        $pendingTransactions = $saving->transactions()->pending()->count();
        if ($pendingTransactions > 0) {
            return ApiResponse::respond(
                message: 'Cannot cancel savings plan with pending transactions. Please wait for them to be processed.',
                status: false,
                statusCode: 400
            );
        }

        $saving->update(['status' => 'cancelled']);

        // Log plan cancellation
        AuditLog::log('savings_plan_cancelled', $saving);

        // Create notification
        $this->notificationService->createInAppNotification(
            $user->id,
            'Savings Plan Cancelled',
            "Your savings plan '{$saving->plan_name}' has been cancelled.",
            'warning'
        );


        return ApiResponse::respond(
            message: 'Savings plan cancelled successfully'
        );
    }

    public function getStatistics(Request $request, $id)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($id);

        // Monthly breakdown
        $monthlyBreakdown = SavingsTransaction::selectRaw('
                    MONTH(created_at) as month,
                    YEAR(created_at) as year,
                    transaction_type,
                    SUM(net_amount) as total_amount,
                    COUNT(*) as transaction_count
                ')
            ->where('savings_id', $saving->id)
            ->where('status', 'completed')
            ->groupBy('year', 'month', 'transaction_type')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // Performance metrics
        $performance = [
            'average_monthly_deposit' => $saving->deposits()->completed()
                ->whereYear('created_at', now()->year)
                ->avg('net_amount') ?? 0,
            'largest_deposit' => $saving->deposits()->completed()->max('net_amount') ?? 0,
            'smallest_deposit' => $saving->deposits()->completed()->min('net_amount') ?? 0,
            'deposit_frequency' => $saving->deposits()->completed()->count(),
            'days_since_creation' => $saving->created_at->diffInDays(now()),
            'projected_completion_date' => $this->calculateProjectedCompletion($saving),
            'savings_velocity' => $this->calculateSavingsVelocity($saving)
        ];

        // Goal achievement metrics
        $achievement = [
            'progress_percentage' => $saving->progress_percentage,
            'amount_saved' => $saving->current_amount,
            'remaining_amount' => $saving->remaining_amount,
            'days_until_due' => $saving->days_until_due,
            'is_on_track' => $this->isOnTrack($saving),
            'recommended_monthly_deposit' => $this->calculateRecommendedMonthlyDeposit($saving)
        ];

        return ApiResponse::respond(
            message: 'Savings statistics retrieved successfully',
            data: [
                'monthly_breakdown' => $monthlyBreakdown,
                'performance' => $performance,
                'achievement' => $achievement,
                'savings_plan' => $saving->only(['id', 'plan_name', 'target_amount', 'current_amount', 'due_date', 'status'])
            ]
        );
    }

    /**
     * Pause a savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pausePlan(PausePlanRequest $request, $id)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($id);

        if (!$saving->isActive()) {
            return ApiResponse::respond(
                message: 'Can only pause active savings plans',
                status: false,
                statusCode: 400
            );
        }

        $saving->update([
            'status' => 'paused',
            'pause_reason' => $request->pause_reason,
            'resume_date' => $request->resume_date
        ]);

        // Log plan pause
        AuditLog::log('savings_plan_paused', $saving, null, [
            'pause_reason' => $request->pause_reason,
            'resume_date' => $request->resume_date
        ]);

        // Create notification
        $this->notificationService->createInAppNotification(
            $user->id,
            'Savings Plan Paused',
            "Your savings plan '{$saving->plan_name}' has been paused. Reason: {$request->pause_reason}",
            'warning'
        );

        return ApiResponse::respond(
            data: $saving->fresh(),
            message: 'Savings plan paused successfully',
        );
    }

    /**
     * Resume a paused savings plan
     * 
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumePlan(Request $request, $id)
    {
        $user = $request->user();
        $saving = RentSaving::where('user_id', $user->id)->findOrFail($id);

        if ($saving->status !== 'paused') {
            return ApiResponse::respond(
                message: 'Can only resume paused savings plans',
                status: false,
                statusCode: 400
            );
        }

        $saving->update([
            'status' => 'active',
            'pause_reason' => null,
            'resume_date' => null
        ]);

        // Log plan resume
        AuditLog::log('savings_plan_resumed', $saving);

        // Create notification
        $this->notificationService->createInAppNotification(
            $user->id,
            'Savings Plan Resumed',
            "Your savings plan '{$saving->plan_name}' has been resumed and is now active.",
            'success'
        );
        return ApiResponse::respond(
            data: $saving->fresh(),
            message: 'Savings plan resumed successfully',
        );
    }

    /**
     * Get savings insights and recommendations
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInsights(Request $request)
    {
        $user = $request->user();

        $insights = [];

        // Check for plans behind schedule
        $behindSchedule = $user->rentSavings()->active()
            ->get()
            ->filter(function ($saving) {
                return !$this->isOnTrack($saving);
            });

        if ($behindSchedule->count() > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Plans Behind Schedule',
                'message' => "You have {$behindSchedule->count()} savings plan(s) behind schedule. Consider increasing your deposits to stay on track.",
                'action' => 'review_plans'
            ];
        }

        // Check for plans due soon
        $dueSoon = $user->rentSavings()->active()
            ->where('due_date', '<=', now()->addDays(30))
            ->count();

        if ($dueSoon > 0) {
            $insights[] = [
                'type' => 'info',
                'title' => 'Plans Due Soon',
                'message' => "You have {$dueSoon} savings plan(s) due within the next 30 days.",
                'action' => 'final_push'
            ];
        }

        // Check savings consistency
        $lastMonthDeposits = $user->savingsTransactions()
            ->deposits()
            ->completed()
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        if ($lastMonthDeposits == 0) {
            $insights[] = [
                'type' => 'suggestion',
                'title' => 'Stay Consistent',
                'message' => 'You haven\'t made any deposits in the last month. Regular deposits help you achieve your goals faster.',
                'action' => 'make_deposit'
            ];
        }

        // Achievement celebration
        $completedThisMonth = $user->rentSavings()
            ->where('status', 'completed')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        if ($completedThisMonth > 0) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Congratulations!',
                'message' => "You've completed {$completedThisMonth} savings plan(s) this month. Great job!",
                'action' => 'celebrate'
            ];
        }

        return ApiResponse::respond(
            data: $insights,
            message: 'Savings insights retrieved successfully'
        );
    }


    /**
     * Export savings data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exportData(Request $request)
    {
        $user = $request->user();

        $savingsPlans = $user->rentSavings()->with(['property', 'transactions'])->get();

        $exportData = [
            'user_info' => [
                'name' => $user->full_name,
                'email' => $user->email,
                'export_date' => now()->format('Y-m-d H:i:s')
            ],
            'summary' => [
                'total_plans' => $savingsPlans->count(),
                'active_plans' => $savingsPlans->where('status', 'active')->count(),
                'completed_plans' => $savingsPlans->where('status', 'completed')->count(),
                'total_savings' => $savingsPlans->sum('current_amount'),
                'total_target' => $savingsPlans->sum('target_amount')
            ],
            'savings_plans' => $savingsPlans->map(function ($saving) {
                return [
                    'plan_name' => $saving->plan_name,
                    'target_amount' => $saving->target_amount,
                    'current_amount' => $saving->current_amount,
                    'progress_percentage' => $saving->progress_percentage,
                    'status' => $saving->status,
                    'created_date' => $saving->created_at->format('Y-m-d'),
                    'due_date' => $saving->due_date->format('Y-m-d'),
                    'property_title' => $saving->property?->title ?? 'External Property',
                    'total_deposits' => $saving->deposits()->completed()->count(),
                    'total_withdrawals' => $saving->withdrawals()->completed()->count(),
                    'charges_paid' => $saving->transactions()->sum('charge_amount'),
                    'penalties_paid' => $saving->transactions()->sum('penalty_amount')
                ];
            }),
            'transactions' => $user->savingsTransactions()->with('savings')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                        'plan_name' => $transaction->savings->plan_name,
                        'type' => $transaction->transaction_type,
                        'amount' => $transaction->amount,
                        'charges' => $transaction->charge_amount,
                        'penalties' => $transaction->penalty_amount,
                        'net_amount' => $transaction->net_amount,
                        'status' => $transaction->status,
                        'is_early_withdrawal' => $transaction->is_early_withdrawal
                    ];
                })
        ];

        return ApiResponse::respond(
            data: $exportData,
            message: 'Savings data exported successfully'
        );
    }

    //start private methods

    /**
     * Calculate projected completion date
     * 
     * @param RentSaving $saving
     * @return string|null
     */
    private function calculateProjectedCompletion($saving)
    {
        $deposits = $saving->deposits()->completed()->get();

        if ($deposits->count() < 2) {
            return null;
        }

        $averageDeposit = $deposits->avg('net_amount');
        $remainingAmount = $saving->remaining_amount;

        if ($averageDeposit <= 0) {
            return null;
        }

        $monthsToCompletion = ceil($remainingAmount / $averageDeposit);
        $projectedDate = now()->addMonths($monthsToCompletion);

        return $projectedDate->format('Y-m-d');
    }

    /**
     * Calculate savings velocity (amount saved per day)
     * 
     * @param RentSaving $saving
     * @return float
     */
    private function calculateSavingsVelocity($saving)
    {
        $daysSinceCreation = $saving->created_at->diffInDays(now());

        if ($daysSinceCreation <= 0) {
            return 0;
        }

        return $saving->current_amount / $daysSinceCreation;
    }

    /**
     * Calculate recommended monthly deposit
     * 
     * @param RentSaving $saving
     * @return float
     */
    private function calculateRecommendedMonthlyDeposit($saving)
    {
        $monthsRemaining = now()->diffInMonths($saving->due_date);

        if ($monthsRemaining <= 0) {
            return $saving->remaining_amount;
        }

        return $saving->remaining_amount / $monthsRemaining;
    }

    /**
     * Check if savings plan is on track
     * 
     * @param RentSaving $saving
     * @return bool
     */
    private function isOnTrack($saving)
    {
        $totalDays = $saving->created_at->diffInDays($saving->due_date);
        $daysPassed = $saving->created_at->diffInDays(now());

        if ($totalDays <= 0) {
            return true;
        }

        $expectedProgress = ($daysPassed / $totalDays) * 100;
        $actualProgress = $saving->progress_percentage;

        // Allow 10% tolerance
        return $actualProgress >= ($expectedProgress - 10);
    }
}
