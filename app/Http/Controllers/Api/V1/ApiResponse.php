<?php

namespace App\Http\Controllers\Api\V1;

trait ApiResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function success(string $message = 'Success', array $data = [], int $statusCode = 200)
    {
        return response()->json([
            'status' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * @param  mixed  $data
     */
    protected function error(string $message = 'Failed', mixed $data = null, int $statusCode = 422)
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
