<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\EngagementController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use App\Http\Controllers\API\RentPaymentController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/send-onboarding-otp', [AuthController::class, 'sendOnboardingOtp']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
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
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('profile')->group(function () {
    Route::post('/complete-profile', [ProfileController::class, 'completeProfile']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('listing')->group(function () {
    Route::resource('properties', PropertyController::class);
    Route::get('/my-listings', [PropertyController::class, 'myListings']);
    Route::post('/upload-media/{id}', [PropertyController::class, 'uploadMedia']);
    Route::delete('/remove-media/{id}', [PropertyController::class, 'removeMedia']);
    Route::post('/toggle-status/{id}', [PropertyController::class, 'toggleStatus']);
    Route::post('/toggle-favorite/{id}', [PropertyController::class, 'toggleFavorite']);
    Route::get('/favorites', [PropertyController::class, 'getFavorites']);
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
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    Route::get('/recent', [NotificationController::class, 'getRecent']);
    Route::get('/stats', [NotificationController::class, 'getStats']);
    Route::get('/{id}', [NotificationController::class, 'show']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/{id}/unread', [NotificationController::class, 'markAsUnread']);
    Route::patch('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/mark-multiple-read', [NotificationController::class, 'markMultipleAsRead']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/multiple', [NotificationController::class, 'deleteMultiple']);
    Route::delete('/read', [NotificationController::class, 'deleteAllRead']);

    // Admin routes
    Route::post('/', [NotificationController::class, 'store']);
    Route::post('/bulk', [NotificationController::class, 'sendBulk']);
    Route::post('/role', [NotificationController::class, 'sendToRole']);
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('chat')->group(function () {
    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::get('/conversations/{id}', [ChatController::class, 'getConversation']);
    Route::post('/conversations/start', [ChatController::class, 'startConversation']);
    Route::patch('/conversations/{id}', [ChatController::class, 'updateConversation']);
    Route::patch('/conversations/{id}/close', [ChatController::class, 'closeConversation']);
    Route::patch('/conversations/{id}/reopen', [ChatController::class, 'reopenConversation']);
    Route::get('/stats', [ChatController::class, 'getConversationsStats'])->name('chat.stats');
    Route::get('/search', [ChatController::class, 'searchConversations']);
});
