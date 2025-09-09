<?php

namespace App\Util;

use Illuminate\Support\Facades\Log;

Trait ErrorHandler {
    public function throwableErrorHandler(\Throwable $th, $message = "An error occurred")
    {
        // Get the backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        // Get the calling method name
        $callingMethod = $backtrace[1]['function'] ?? 'unknown';
    
        // Get the current date and time and format it
        $currentDateTime = now()->format('Y-m-d g:iA'); // 'g:iA' formats time in 1:30AM format
    
        // Format the log message with date and time
        $logMessage = sprintf(
            '[%s] Error occurred in method: %s. Message: %s',
            $currentDateTime,
            $callingMethod,
            $message
        );
    
        // Log the error with the formatted message
        Log::error($logMessage, ['exception' => $th]);
    
        // Handle the exception
        return ApiResponse::handleException($th, $message);
    }
    
}