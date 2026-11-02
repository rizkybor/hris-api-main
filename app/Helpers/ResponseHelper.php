<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ResponseHelper
{
    public static function jsonResponse($success, $message, $data, $statusCode): JsonResponse
    {
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
