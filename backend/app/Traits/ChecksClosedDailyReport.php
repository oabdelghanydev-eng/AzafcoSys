<?php

namespace App\Traits;

use App\Exceptions\BusinessException;
use App\Models\DailyReport;

/**
 * Trait to prevent modifications to transactions on closed fiscal days.
 * 
 * Apply this trait to any model that has a date field and should respect
 * the DailyReport immutability rule (Invoice, Collection, Expense, ReturnModel).
 * 
 * ARCHITECTURAL DECISION (2025-12-27):
 * Strict immutability chosen to ensure audit compliance and prevent
 * retroactive modifications that could compromise financial integrity.
 */
trait ChecksClosedDailyReport
{
    /**
     * Boot the trait and register model event listeners.
     */
    public static function bootChecksClosedDailyReport(): void
    {
        static::updating(function ($model) {
            static::checkDayNotClosed($model, 'update');
        });

        static::deleting(function ($model) {
            static::checkDayNotClosed($model, 'delete');
        });
    }

    /**
     * Check if the model's date falls on a closed daily report.
     *
     * @throws BusinessException If day is closed
     */
    protected static function checkDayNotClosed($model, string $action): void
    {
        $dateField = $model->getDateFieldForDailyReportCheck();
        $date = $model->{$dateField};

        if (!$date) {
            return; // No date set, skip check
        }

        // Normalize to date string
        $dateString = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : $date;

        $closedReport = DailyReport::where('date', $dateString)
            ->where('status', 'closed')
            ->exists();

        if ($closedReport) {
            throw new BusinessException(
                'DAY_001',
                'لا يمكن تعديل أو حذف معاملة في يوم مغلق. يرجى إعادة فتح اليومية أولاً.',
                "Cannot {$action} transaction on a closed day. Please reopen the daily report first."
            );
        }
    }

    /**
     * Get the date field name used for daily report checks.
     * Override in the model if the date field has a different name.
     */
    public function getDateFieldForDailyReportCheck(): string
    {
        return 'date';
    }
}
