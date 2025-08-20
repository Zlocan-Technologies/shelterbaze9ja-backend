<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\PropertyController;
use Illuminate\Support\Facades\Route;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::patch('/user/update', [AuthController::class, 'updateUser']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
    Route::post('/email/resend-verification', [AuthController::class, 'resendVerificationEmail']);
    Route::post('/email/change', [AuthController::class, 'changeEmail']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);
});

Route::middleware(['auth:sanctum'])->prefix('auth')->group(function () {
    Route::post('/refreshToken', [AuthController::class, 'refreshToken']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:sanctum'])->prefix('profile')->group(function () {
    Route::post('/complete-profile', [ProfileController::class, 'completeProfile']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum'])->prefix('listing')->group(function () {
    Route::resource('/', PropertyController::class);
    Route::get('/my-listings', [PropertyController::class, 'myListings']);
    Route::post('/upload-media/{id}', [PropertyController::class, 'uploadMedia']);
    Route::delete('/remove-media/{id}', [PropertyController::class, 'removeMedia']);
    Route::post('/toggle-status/{id}', [PropertyController::class, 'toggleStatus']);
    Route::post('/toggle-favorite/{id}', [PropertyController::class, 'toggleFavorite']);
    Route::get('/favorites', [PropertyController::class, 'getFavorites']);
});

Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {
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

Route::middleware(['auth:sanctum'])->prefix('chat')->group(function () {
    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::get('/conversations/{id}', [ChatController::class, 'getConversation']);
    Route::post('/conversations/start', [ChatController::class, 'startConversation']);
    Route::patch('/conversations/{id}', [ChatController::class, 'updateConversation']);
    Route::patch('/conversations/{id}/close', [ChatController::class, 'closeConversation']);
    Route::patch('/conversations/{id}/reopen', [ChatController::class, 'reopenConversation']);
    Route::get('/conversations/stats', [ChatController::class, 'getConversationsStats']);
    Route::get('/search', [ChatController::class, 'searchConversations']);
});
