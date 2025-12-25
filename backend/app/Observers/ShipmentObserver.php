<?php

namespace App\Observers;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use App\Models\Shipment;
use App\Services\AuditService;

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
            // Get all changed fields except timestamps and settlement totals
            $changedFields = array_diff(
                array_keys($shipment->getDirty()),
                [
                    'updated_at',
                    'status',
                    'settled_at',                   // Settlement timestamp
                    'settled_by',                   // Settlement user
                    'total_sales',                  // Settlement calculated fields
                    'total_wastage',                // Settlement calculated fields
                    'total_carryover_out',          // Settlement calculated fields
                    'total_supplier_expenses',      // Settlement calculated fields
                    'previous_supplier_balance',    // Balance tracking (can be backfilled)
                    'final_supplier_balance',       // Balance tracking (can be backfilled)
                ]
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
     * Observer only logs changes - Service handles all business logic
     */
    public function updated(Shipment $shipment): void
    {
        // Service handles all settlement logic (status, totals, timestamps)
        // Observer responsibility: Only log the change
        AuditService::logUpdate($shipment, $shipment->getOriginal());
    }

    /**
     * Handle the Shipment "deleting" event.
     * Prevents deletion if has invoices
     */
    public function deleting(Shipment $shipment): bool
    {
        // Check if any items have been sold (cartons-based)
        $hasSales = $shipment->items()
            ->where('sold_cartons', '>', 0)
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
