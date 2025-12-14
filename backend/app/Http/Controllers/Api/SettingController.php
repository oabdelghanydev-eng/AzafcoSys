<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
                    ]
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
                    'message_en' => 'Setting not found'
                ]
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
