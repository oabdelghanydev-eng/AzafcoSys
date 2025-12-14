<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Services\AuditService;
use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;

class ShipmentObserver
{
    /**
     * Handle the Shipment "updating" event.
     * Validates status transitions and protects settled shipments
     * تصحيح 2025-12-13: منع تعديل أي حقل في الشحنة المُصفاة (BR-SHP-007)
     */
    public function updating(Shipment $shipment): void
    {
        // fifo_sequence is ALWAYS immutable (Best Practice)
        if ($shipment->isDirty('fifo_sequence')) {
            throw new BusinessException(
                ErrorCodes::SHP_001,
                'fifo_sequence غير قابل للتعديل - يُستخدم للترتيب المحاسبي',
                'fifo_sequence is immutable - used for accounting order'
            );
        }

        $originalStatus = $shipment->getOriginal('status');

        // If shipment is settled, only allow specific changes
        if ($originalStatus === 'settled') {
            // Get all changed fields except timestamps
            $changedFields = array_diff(
                array_keys($shipment->getDirty()),
                ['updated_at', 'status']
            );

            // If any field other than status/timestamps changed, block it
            if (!empty($changedFields)) {
                throw new BusinessException(
                    ErrorCodes::SHP_001,
                    ErrorCodes::getMessage(ErrorCodes::SHP_001) . '. الحقول: ' . implode(', ', $changedFields),
                    ErrorCodes::getMessageEn(ErrorCodes::SHP_001)
                );
            }

            // Only unsettle operation can change status (settled -> closed)
            $newStatus = $shipment->status;
            if ($shipment->isDirty('status') && $newStatus !== 'closed') {
                throw new BusinessException(
                    ErrorCodes::SHP_004,
                    ErrorCodes::getMessage(ErrorCodes::SHP_004),
                    ErrorCodes::getMessageEn(ErrorCodes::SHP_004)
                );
            }
        }
    }

    /**
     * Handle the Shipment "updated" event.
     * Logs the change
     */
    public function updated(Shipment $shipment): void
    {
        // If settling, record settlement info
        if ($shipment->wasChanged('status') && $shipment->status === 'settled') {
            $shipment->settled_at = now();
            $shipment->settled_by = auth()->id();
            $shipment->saveQuietly();
        }

        AuditService::logUpdate($shipment, $shipment->getOriginal());
    }

    /**
     * Handle the Shipment "deleting" event.
     * Prevents deletion if has invoices
     */
    public function deleting(Shipment $shipment): bool
    {
        // Check if any items have been sold
        $hasSales = $shipment->items()
            ->where('sold_quantity', '>', 0)
            ->exists();

        if ($hasSales) {
            throw new BusinessException(
                ErrorCodes::SHP_002,
                ErrorCodes::getMessage(ErrorCodes::SHP_002),
                ErrorCodes::getMessageEn(ErrorCodes::SHP_002)
            );
        }

        // If settled, can't delete
        if ($shipment->status === 'settled') {
            throw new BusinessException(
                ErrorCodes::SHP_001,
                ErrorCodes::getMessage(ErrorCodes::SHP_001),
                ErrorCodes::getMessageEn(ErrorCodes::SHP_001)
            );
        }

        AuditService::logDelete($shipment);

        return true;
    }
}
