<?php

use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\EngagementController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RentPaymentController;
use App\Http\Controllers\API\RentSavingsController;
use App\Http\Controllers\API\SupportController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/send-onboarding-otp', [AuthController::class, 'sendOnboardingOtp']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
    
    // Biometric authentication routes
    Route::post('/biometric/authenticate', [BiometricController::class, 'authenticate']);
});

Route::prefix('/savings')->group(function () {
    Route::get('verify/{reference}', [RentSavingsController::class, 'verifyDeposit'])->name('rent_saving.verify');
});

Route::prefix('engagement')->group(function () {
    Route::post('/initialize', [EngagementController::class, 'initiatePayment'])->middleware(['auth:sanctum', 'verified']);
    Route::get('/verify/{reference}', [EngagementController::class, 'verifyPayment'])->name('engagement.verify');
    Route::get('/contact/{propertyId}', [EngagementController::class, 'getPropertyContact'])->middleware(['auth:sanctum', 'verified']);
    Route::get('/my-engagements', [EngagementController::class, 'myEngagements'])->middleware(['auth:sanctum', 'verified']);
    Route::get('/interested-tenants/{propertyId}', [EngagementController::class, 'getInterestedTenants'])->middleware(['auth:sanctum', 'verified']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('auth')->group(function () {
    Route::post('/refreshToken', [AuthController::class, 'refreshToken']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Protected biometric routes
    // Route::prefix('biometric')->group(function () {
    //     Route::post('/enroll', [BiometricController::class, 'enroll']);
    //     Route::delete('/disable', [BiometricController::class, 'disable']);
    //     Route::get('/status', [BiometricController::class, 'status']);
    //     Route::get('/attempts', [BiometricController::class, 'attempts']);
    //     Route::put('/reenroll', [BiometricController::class, 'reenroll']);
    // });
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('profile')->group(function () {
    Route::post('/complete-profile', [ProfileController::class, 'completeProfile']);
    Route::post('/upload-document', [ProfileController::class, 'uploadDocument']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update', [ProfileController::class, 'update']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('/create-transaction-pin', [ProfileController::class, 'createTransactionPin']);
    Route::post('/reset-transaction-pin', [ProfileController::class, 'resetTransactionPin']);
    Route::post('/forgot-transaction-pin', [ProfileController::class, 'forgotTransactionPin']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::post('/create-withdrawal', [ProfileController::class, 'requestWithdrawal']);
    Route::get('/withdrawal-history', [ProfileController::class, 'getWithdrawalHistory']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('listing')->group(function () {
    Route::resource('properties', PropertyController::class);
    Route::post('/properties/{id}', [PropertyController::class, 'update']);
    Route::get('/my-listings', [PropertyController::class, 'myListings']);
    Route::post('/upload-media/{id}', [PropertyController::class, 'uploadMedia']);
    Route::delete('/remove-media/{id}', [PropertyController::class, 'removeMedia']);
    Route::post('/toggle-status/{id}', [PropertyController::class, 'toggleStatus']);
    Route::post('/toggle-favorite/{id}', [PropertyController::class, 'toggleFavorite']);
    Route::get('/favorites', [PropertyController::class, 'getFavorites']);
    Route::get('/booked-apartments', [PropertyController::class, 'getBookedApartments']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('rent')->group(function () {
    Route::post('/generate-invoice', [RentPaymentController::class, 'generateInvoice']); //done
    Route::post('/upload-payment-proof', [RentPaymentController::class, 'uploadPaymentProof']); //ddone
    Route::get('/payment-history', [RentPaymentController::class, 'getPaymentHistory']); //done
    Route::get('/my-apartments', [RentPaymentController::class, 'getMyApartments']); //done
    Route::get('/bank-details', [RentPaymentController::class, 'getBankDetails']); //done
    Route::get('/payment-summary', [RentPaymentController::class, 'getPaymentSummary']); //done
    Route::post('/renewal', [RentPaymentController::class, 'requestRenewal']);
    Route::post('/report-issue', [RentPaymentController::class, 'reportIssue']);
    Route::post('/early-termination', [RentPaymentController::class, 'requestEarlyTermination']); //done
    Route::post('/cancel-rental/{id}', [RentPaymentController::class, 'cancelRentalRequest']); // done
    Route::get('/rental-details/{id}', [RentPaymentController::class, 'getRentalDetails']); //dome
    Route::get('/rental-agreement/{id}', [RentPaymentController::class, 'getRentalAgreement']); //done
    Route::get('/payment-receipt/{id}', [RentPaymentController::class, 'getPaymentReceipt']); //done
    Route::get('/export-rental-data', [RentPaymentController::class, 'exportRentalData']); //done
    Route::get('/insights', [RentPaymentController::class, 'getRentalInsights']); // done
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('notifications')->group(function () {
    // User routes
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.unread_count');
    // Route::get('/recent', [NotificationController::class, 'getRecent']);
    // Route::get('/stats', [NotificationController::class, 'getStats']);
    // Route::get('/{id}', [NotificationController::class, 'show']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
    // Route::patch('/{id}/unread', [NotificationController::class, 'markAsUnread']);
    Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    // Route::patch('/mark-multiple-read', [NotificationController::class, 'markMultipleAsRead']);
    // Route::delete('/{id}', [NotificationController::class, 'destroy']);
    // Route::delete('/multiple', [NotificationController::class, 'deleteMultiple']);
    // Route::delete('/read', [NotificationController::class, 'deleteAllRead']);

    // Admin routes
//     Route::post('/', [NotificationController::class, 'store']);
//     Route::post('/bulk', [NotificationController::class, 'sendBulk']);
//     Route::post('/role', [NotificationController::class, 'sendToRole']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('chat')->group(function () {
    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::get('/conversations/{id}', [ChatController::class, 'getConversation']);
    Route::post('/conversations/start', [ChatController::class, 'startConversation']);
    Route::post('/send-message', [ChatController::class, 'sendMessage']);
    Route::patch('/conversations/{id}', [ChatController::class, 'updateConversation']);
    Route::patch('/conversations/{id}/close', [ChatController::class, 'closeConversation']);
    Route::patch('/conversations/{id}/reopen', [ChatController::class, 'reopenConversation']);
    Route::get('/stats', [ChatController::class, 'getConversationsStats'])->name('chat.stats');
    Route::get('/search', [ChatController::class, 'searchConversations']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('savings')->group(function () {
    Route::get('/', [RentSavingsController::class, 'index']); // done
    Route::post('/', [RentSavingsController::class, 'store']); // done
    Route::get('/{id}', [RentSavingsController::class, 'show']); // done
    Route::patch('/{id}', [RentSavingsController::class, 'update']); //done
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('savings-mgt')->group(function () {
    Route::post('/deposit', [RentSavingsController::class, 'deposit']); // done
    Route::get('/verify-deposit', [RentSavingsController::class, 'verifyDeposit']); // done
    Route::post('/withdraw', [RentSavingsController::class, 'withdraw']);
    Route::get('/transaction-history/{savingsId}', [RentSavingsController::class, 'getTransactionHistory']); // done
    Route::get('/dashboard', [RentSavingsController::class, 'dashboard']); // done
    Route::post('/cancel-plan/{id}', [RentSavingsController::class, 'cancelPlan']);
    Route::post('/pause-plan/{id}', [RentSavingsController::class, 'pausePlan']);
    Route::post('/resume-plan/{id}', [RentSavingsController::class, 'resumePlan']);
    Route::get('/insights', [RentSavingsController::class, 'getInsights']); // sonw
});

//routes for agent module
Route::middleware(['auth:sanctum', 'verified'])->prefix('agents')->group(function () {
    Route::get('/assigned-properties', [AgentController::class, 'getAssignedProperties']); //done
    Route::post('/verify-property', [AgentController::class, 'verifyProperty']); //done
    Route::get('/assigned-landlords', [AgentController::class, 'getAssignedLandlords']);//done
    Route::post('/manage-listing-for-landlord', [AgentController::class, 'manageListingForLandlord']);
    Route::get('/stats', [AgentController::class, 'getAgentStats']); // done
    Route::post('/verify-agent', [AgentController::class, 'verifyAgent']); //done // confirm agent's identity using their ID

    Route::post('/update-availability', [AgentController::class, 'updateAgentAvailability']); //done

    Route::get('/earnings', [AgentController::class, 'getAgentEarnings']); //done
    Route::post('/submit-report', [AgentController::class, 'submitAgentReport']);
});

Route::prefix('/support')->group(function () {
    Route::get('/faqs', [SupportController::class, 'getFaqs']);
    Route::get('/amenities', [SupportController::class, 'getAmenities']);
});
