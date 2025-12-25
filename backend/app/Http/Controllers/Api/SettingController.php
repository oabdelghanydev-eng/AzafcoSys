<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Setting
 */
class SettingController extends Controller
{
    /**
     * Get all settings grouped by category
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all();

        // Group by group field
        $grouped = $settings->groupBy('group')->map(function ($groupSettings) {
            return $groupSettings->mapWithKeys(function ($setting) {
                return [
                    $setting->key => [
                        'value' => $this->castValue($setting->value, $setting->type),
                        'type' => $setting->type,
                        'description' => $setting->description,
                    ],
                ];
            });
        });

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Update settings (bulk update)
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:settings,key',
            'settings.*.value' => 'required',
        ]);

        $updated = [];

        foreach ($validated['settings'] as $settingData) {
            $setting = Setting::where('key', $settingData['key'])->first();

            if ($setting) {
                $setting->value = $this->prepareValue($settingData['value'], $setting->type);
                $setting->save();
                $updated[] = $setting->key;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الإعدادات بنجاح',
            'updated' => $updated,
        ]);
    }

    /**
     * Get a single setting value
     */
    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SET_001',
                    'message' => 'الإعداد غير موجود',
                    'message_en' => 'Setting not found',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $setting->key,
                'value' => $this->castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
            ],
        ]);
    }

    /**
     * Reset database - Admin only with password confirmation
     * 
     * WARNING: This will DELETE ALL DATA and reseed the database!
     */
    public function resetDatabase(Request $request, \App\Services\DatabaseResetService $service): JsonResponse
    {
        // 1. Check environment first (most important)
        if (!$service->isAllowedEnvironment()) {
            return $this->errorResponse(
                'SET_005',
                'غير مسموح في هذه البيئة',
                'Database reset is not allowed in ' . config('app.env') . ' environment',
                403
            );
        }

        // 2. Check admin access
        $user = auth()->user();
        if (!$user || !$user->is_admin) {
            return $this->errorResponse('AUTH_001', 'غير مصرح - يجب أن تكون مسؤول', 'Unauthorized - Admin access required', 403);
        }

        // 3. Validate request
        $validated = $request->validate(['password' => 'required|string|min:8']);

        // 4. Check password is configured
        if (!$service->isConfigured()) {
            return $this->errorResponse('SET_002', 'لم يتم تكوين كلمة مرور إعادة التعيين', 'Reset password not configured in .env (min 8 chars)', 500);
        }

        // 5. Validate password
        if (!$service->validatePassword($validated['password'])) {
            return $this->errorResponse('SET_003', 'كلمة المرور غير صحيحة', 'Invalid password', 401);
        }

        // 6. Execute reset
        try {
            $service->execute($user->id, $user->name, $request->ip());

            return response()->json([
                'success' => true,
                'message' => 'تم إعادة تهيئة قاعدة البيانات بنجاح',
                'message_en' => 'Database reset completed successfully',
                'warning' => 'All data has been deleted. Please login again.',
            ]);

        } catch (\Exception $e) {
            $service->logFailure($user->id, $e->getMessage());
            return $this->errorResponse('SET_004', 'فشل في إعادة تهيئة قاعدة البيانات', 'Database reset failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create standardized error response
     */
    private function errorResponse(string $code, string $messageAr, string $messageEn, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $messageAr,
                'message_en' => $messageEn,
            ],
        ], $status);
    }

    /**
     * Cast value based on type
     */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Prepare value for storage
     */
    private function prepareValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
}
