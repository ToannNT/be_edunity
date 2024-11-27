<?php
if (!function_exists('formatResponse')) {
    function formatResponse(
        $status = STATUS_OK,
        $data = null,
        $error = null,
        $message = null,
        $code = null,
        $token = null,
        $refreshToken = null
    ): \Illuminate\Http\JsonResponse {
        $response = [
            'status' => $status,
            'data' => $data,
            'error' => $error,
            'message' => $message,
            'code' => $code,
        ];

        // Thêm thông tin token nếu có
        if ($token) {
            $response['access_token'] = $token;
            $response['refresh_token'] = $refreshToken;
            $response['token_type'] = 'bearer';
            $response['expires_in'] = auth('api')->factory()->getTTL() * 60;
        }

        return response()->json($response);
    }
}
