<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;

trait HandleApi
{
    /**
     * Gửi response thành công (Success).
     *
     * @param  array  $data    Dữ liệu trả về (mảng, object, v.v.)
     * @param  string $message Tin nhắn
     * @param  int    $code    HTTP Status Code (mặc định 200)
     */
    protected function sendSuccess(array$data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $message,
        ], $code);
    }

    /**
     * Gửi response lỗi xác thực (Validation Error).
     * @param string $message
     * @param array $errors
     * @param int $code
     * @return JsonResponse
     */
    protected function sendValidation(string $message = 'Validation Error', array $errors = [], int $code = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Gửi response lỗi (Error).
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function sendError(string $message = 'Error', int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $code);
    }
}
