<?php

namespace FancyCrud\Traits;

use Illuminate\Http\JsonResponse;

trait ResponseTrait
{
    public function response(mixed $data = [], int $statusCode = 200, string $message = 'success', array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'status' => in_array($statusCode, [200, 201, 204, 205]),
            'message' => $message,
            'code' => $statusCode,
            'meta' => $meta,
        ], $statusCode);
    }

    public function success(mixed $data = [], int $statusCode = 200, ?string $message = null, array $meta = []): JsonResponse
    {
        return $this->response($data, $statusCode, $message ?? __('messages.success'), $meta);
    }

    public function error(mixed $data = [], int $statusCode = 400, ?string $message = null): JsonResponse
    {
        return $this->response($data, $statusCode, $message ?? __('messages.error'));
    }

    public function notFound(?string $message = null): JsonResponse
    {
        return $this->error(null, 404, $message ?? __('messages.not_found'));
    }
}
