<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Log a create action
     */
    public static function logCreate(Model $model): void
    {
        self::log('created', $model, null, $model->toArray());
    }

    /**
     * Log an update action
     */
    public static function logUpdate(Model $model, array $oldValues): void
    {
        // Filter only changed values
        $newValues = [];
        foreach ($oldValues as $key => $value) {
            if ($model->{$key} !== $value) {
                $newValues[$key] = $model->{$key};
            }
        }

        if (!empty($newValues)) {
            self::log('updated', $model, $oldValues, $newValues);
        }
    }

    /**
     * Log a delete action
     */
    public static function logDelete(Model $model): void
    {
        self::log('deleted', $model, $model->toArray(), null);
    }

    /**
     * Log a cancel action (for invoices)
     */
    public static function logCancel(Model $model): void
    {
        self::log('cancelled', $model, ['status' => 'active'], ['status' => 'cancelled']);
    }

    /**
     * Log a correction action
     */
    public static function logCorrection(string $action, Model $model, array $details): void
    {
        self::log($action, $model, null, $details);
    }

    /**
     * Log an adjustment action (for inventory adjustments)
     */
    public static function logAdjustment(string $action, Model $model, array $details): void
    {
        self::log($action, $model, null, $details);
    }

    /**
     * Core logging method
     */
    private static function log(
        string $action,
        Model $model,
        ?array $oldValues,
        ?array $newValues
    ): void {
        // Filter out sensitive and unnecessary fields
        $excludeFields = ['password', 'remember_token', 'updated_at'];

        if ($oldValues) {
            $oldValues = array_diff_key($oldValues, array_flip($excludeFields));
        }

        if ($newValues) {
            $newValues = array_diff_key($newValues, array_flip($excludeFields));
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Get audit trail for a model
     */
    public static function getTrail(string $modelType, int $modelId): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();
    }
}
