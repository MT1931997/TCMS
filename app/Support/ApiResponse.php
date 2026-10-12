<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Return a standardized successful JSON response.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'تمت العملية بنجاح', int $status = 200, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a standardized error JSON response.
     */
    public static function error(string $message = 'حدث خطأ أثناء معالجة الطلب', int $status = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
