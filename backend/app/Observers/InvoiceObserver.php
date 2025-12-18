<?php

namespace App\Observers;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AuditService;
use App\Services\FifoAllocatorService;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    private FifoAllocatorService $fifoService;

    public function __construct(FifoAllocatorService $fifoService)
    {
        $this->fifoService = $fifoService;
    }

    /**
     * Handle the Invoice "created" event.
     * Note: Customer balance is updated by InvoiceController::store()
     * after totals are calculated (not here, as Observer fires before total is set)
     */
    public function created(Invoice $invoice): void
    {
        // Wastage: set balance to 0 (no receivable)
        if ($invoice->type === 'wastage') {
            $invoice->balance = 0;
            $invoice->saveQuietly();
        }

        AuditService::logCreate($invoice);
    }

    /**
     * Handle the Invoice "updated" event.
     * Handles cancellation logic
     */
    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status')) {
            $oldStatus = $invoice->getOriginal('status');
            $newStatus = $invoice->status;

            // Cancellation: active -> cancelled
            if ($oldStatus === 'active' && $newStatus === 'cancelled') {
                $this->handleCancellation($invoice);
            }

            // Prevent reactivation
            if ($oldStatus === 'cancelled' && $newStatus === 'active') {
                throw new BusinessException(
                    ErrorCodes::INVOICE_002,
                    ErrorCodes::getMessage(ErrorCodes::INVOICE_002),
                    ErrorCodes::getMessageEn(ErrorCodes::INVOICE_002)
                );
            }
        }

        AuditService::logUpdate($invoice, $invoice->getOriginal());
    }

    /**
     * Handle invoice cancellation
     * 1. Reverse FIFO allocations
     * 2. Delete collection allocations (observers handle balance updates)
     * 3. Decrease customer balance by total
     */
    private function handleCancellation(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // 1. Reverse FIFO allocations (return stock to inventory)
            foreach ($invoice->items as $item) {
                $this->fifoService->reverseAllocation($item->id);
            }

            // 2. Delete collection allocations (their observer increases invoice balance)
            // But since we're cancelling, we need to handle differently
            $allocatedAmount = $invoice->allocations()->sum('amount');
            $invoice->allocations()->delete();

            // 3. Decrease customer balance by total (except for wastage)
            if ($invoice->type !== 'wastage') {
                Customer::where('id', $invoice->customer_id)
                    ->decrement('balance', (float) $invoice->total);
            }

            // 4. If there was allocated amount, it becomes unallocated in collections
            // The collection's unallocated amount will be updated by collection recalculation

            // 5. Zero out invoice amounts - use string to avoid float precision issues
            $invoice->paid_amount = '0.00';
            $invoice->balance = '0.00';
            $invoice->saveQuietly();

            AuditService::logCancel($invoice);
        });
    }

    /**
     * Handle the Invoice "deleting" event.
     * Deletion is PROHIBITED - always throws exception
     */
    public function deleting(Invoice $invoice): bool
    {
        throw new BusinessException(
            ErrorCodes::INVOICE_001,
            ErrorCodes::getMessage(ErrorCodes::INVOICE_001),
            ErrorCodes::getMessageEn(ErrorCodes::INVOICE_001)
        );
    }
}
