<?php

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