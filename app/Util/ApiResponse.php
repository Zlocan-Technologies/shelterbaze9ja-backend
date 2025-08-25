<?php

namespace App\Util;


use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ApiResponse
{

    /**
     * Dynamic response method
     */
    public static function respond(
        bool $status = true,
        string $message = 'Success!',
        int $statusCode = 200,
        $data = null,
        $error = null, // Single error message or null
        $errors = null // Array of errors or null
    ): JsonResponse {
        // Determine the correct errors field to use
        $responseErrors = $errors !== null ? $errors : ($error !== null ? [$error] : null);

        $response = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'error' => $error,
            'errors' => $responseErrors // Include errors
        ];
        
        return response()->json($response, $statusCode);
    }

    /**
     * Handle exceptions and return appropriate response
     */
    public static function handleException(
        Throwable $ex,
        string $customMessage = null // Optional custom message
    ): JsonResponse {
        if ($ex instanceof ValidationException) {
           
            return self::respond(
                false,
                $customMessage ?? 'Validation failed', // Use custom message if provided
                422,
                null,
                $ex->getMessage(),
                $ex->errors() // Array of errors
            );
        }

        if ($ex instanceof NotFoundHttpException) {
            return self::respond(
                false,
                $customMessage ?? 'Resource not found', // Use custom message if provided
                404,
                null,
                $ex->getMessage() // Single error message
            );
        }

       
        // Default for all other exceptions
        $code = ((int) $ex->getCode() == 42 || $ex->getCode() == 0 || $ex->getCode() > 600) ? 500 : $ex->getCode();

        return self::respond(
            false,
            $customMessage ?? 'An error occurred', // Use custom message if provided
            (int) $code ?? 500,
            null,
            $ex->getMessage() // Single error message
        );
    }
}
