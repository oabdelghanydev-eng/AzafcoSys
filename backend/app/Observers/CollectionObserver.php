<?php

namespace App\Observers;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use App\Models\Collection;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\CollectionDistributorService;

class CollectionObserver
{
    private CollectionDistributorService $distributorService;

    public function __construct(CollectionDistributorService $distributorService)
    {
        $this->distributorService = $distributorService;
    }

    /**
     * Handle the Collection "created" event.
     * 1. Decrease customer balance
     * 2. Auto-distribute to invoices if auto mode
     */
    public function created(Collection $collection): void
    {
        // Decrease customer balance (customer is paying, so balance decreases)
        Customer::where('id', $collection->customer_id)
            ->decrement('balance', (float) $collection->amount);

        // تصحيح 2025-12-13: distribute if not manual (supports oldest_first and newest_first)
        if ($collection->distribution_method !== 'manual') {
            $this->distributorService->distributeAuto($collection);
        }

        AuditService::logCreate($collection);
    }

    /**
     * Handle the Collection "updated" event.
     * Handles cancellation logic
     * تصحيح 2025-12-13: إلغاء بدلاً من حذف
     */
    public function updated(Collection $collection): void
    {
        if ($collection->wasChanged('status')) {
            $oldStatus = $collection->getOriginal('status');
            $newStatus = $collection->status;

            // Cancellation: confirmed -> cancelled
            if ($oldStatus === 'confirmed' && $newStatus === 'cancelled') {
                $this->handleCancellation($collection);
            }

            // Prevent reactivation
            if ($oldStatus === 'cancelled' && $newStatus === 'confirmed') {
                throw new BusinessException(
                    ErrorCodes::COL_002,
                    ErrorCodes::getMessage(ErrorCodes::COL_002),
                    ErrorCodes::getMessageEn(ErrorCodes::COL_002)
                );
            }
        }

        AuditService::logUpdate($collection, $collection->getOriginal());
    }

    /**
     * Handle collection cancellation
     * 1. Reverse allocations (invoice balances restored by allocation observer)
     * 2. Increase customer balance back
     * تصحيح 2025-12-13: إضافة transaction للذرية
     */
    private function handleCancellation(Collection $collection): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($collection) {
            // 1. Delete allocations (their observer increases invoice balances)
            $collection->allocations()->delete();

            // 2. Increase customer balance back
            Customer::where('id', $collection->customer_id)
                ->increment('balance', (float) $collection->amount);
        });

        AuditService::logCancel($collection);
    }

    /**
     * Handle the Collection "deleting" event.
     * Deletion is PROHIBITED - always throws exception
     * تصحيح 2025-12-13: منع الحذف نهائياً (BR-COL-007)
     */
    public function deleting(Collection $collection): bool
    {
        throw new BusinessException(
            ErrorCodes::COL_001,
            ErrorCodes::getMessage(ErrorCodes::COL_001),
            ErrorCodes::getMessageEn(ErrorCodes::COL_001)
        );
    }
}
