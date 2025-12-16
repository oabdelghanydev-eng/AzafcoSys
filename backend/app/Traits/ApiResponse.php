<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardized API Response Trait
 *
 * Implements the error response format from API_Error_Codes.md:
 * {
 *     "success": false,
 *     "error": {
 *         "code": "INV_001",
 *         "message": "رسالة بالعربية",
 *         "message_en": "English message",
 *         "details": {}
 *     }
 * }
 */
trait ApiResponse
{
    /**
     * Return a success response
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Return an error response following the documented format
     */
    protected function error(
        string $code,
        string $message,
        string $messageEn,
        int $status = 400,
        array $details = []
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'message_en' => $messageEn,
        ];

        if (! empty($details)) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }

    /**
     * Return a validation error response
     */
    protected function validationError(array $errors): JsonResponse
    {
        return $this->error(
            'VAL_001',
            'بيانات غير صحيحة',
            'Validation failed',
            422,
            $errors
        );
    }

    /**
     * Return a not found error
     */
    protected function notFound(string $resource = 'المورد'): JsonResponse
    {
        return $this->error(
            'NOT_FOUND',
            "{$resource} غير موجود",
            'Resource not found',
            404
        );
    }

    /**
     * Return an unauthorized error
     */
    protected function unauthorized(): JsonResponse
    {
        return $this->error(
            'AUTH_001',
            'غير مُسجل دخول',
            'Unauthorized',
            401
        );
    }

    /**
     * Return a forbidden error
     */
    protected function forbidden(?string $reason = null): JsonResponse
    {
        return $this->error(
            'AUTH_002',
            $reason ?? 'غير مصرح بهذه العملية',
            'Forbidden',
            403
        );
    }

    /**
     * Common business rule errors
     */
    protected function invoiceError(string $code, string $message, string $messageEn): JsonResponse
    {
        return $this->error($code, $message, $messageEn, 422);
    }

    protected function collectionError(string $code, string $message, string $messageEn): JsonResponse
    {
        return $this->error($code, $message, $messageEn, 422);
    }

    protected function shipmentError(string $code, string $message, string $messageEn): JsonResponse
    {
        return $this->error($code, $message, $messageEn, 422);
    }

    /**
     * Check if user has permission, throw exception if not
     * Centralizes permission checking across all controllers
     */
    protected function checkPermission(string $permission): void
    {
        if (! auth()->user()->hasPermission($permission)) {
            throw new \App\Exceptions\BusinessException(
                'AUTH_003',
                'ليس لديك صلاحية لهذه العملية',
                'Permission denied'
            );
        }
    }

    /**
     * Ensure user is admin
     */
    protected function ensureAdmin(): void
    {
        if (! auth()->user()->is_admin) {
            throw new \App\Exceptions\BusinessException(
                'AUTH_004',
                'هذه العملية متاحة للمديرين فقط',
                'Admin access only'
            );
        }
    }
}
