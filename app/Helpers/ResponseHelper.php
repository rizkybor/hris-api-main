<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ResponseHelper
{
    public static function jsonResponse($success, $message, $data, $statusCode): JsonResponse
    {
        // Callers often pass $e->getCode(), which for database errors is an
        // SQLSTATE string ("42S22") and for many exceptions 0. Anything that
        // isn't a real HTTP status becomes a 500 (and is logged below).
        if (! is_int($statusCode) || $statusCode < 100 || $statusCode > 599) {
            $statusCode = is_numeric($statusCode) && (int) $statusCode >= 100 && (int) $statusCode <= 599 ? (int) $statusCode : 500;
            if ($statusCode === 500 && is_string($message) && ! str_starts_with($message, 'Internal Server Error')) {
                $message = 'Internal Server Error: '.$message;
            }
        }

        // Controllers return 'Internal Server Error: '.$e->getMessage() on
        // failure. Outside debug mode the exception text (SQL, table names,
        // file paths) is logged instead of being sent to the client.
        if ($statusCode >= 500 && is_string($message) && str_starts_with($message, 'Internal Server Error') && ! config('app.debug')) {
            Log::error($message, ['url' => request()?->fullUrl(), 'user_id' => request()?->user()?->id]);
            $message = 'Internal Server Error';
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
