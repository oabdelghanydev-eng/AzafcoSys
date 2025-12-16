<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\ReturnModel;
use App\Services\AuditService;

class ReturnObserver
{
    /**
     * Handle the Return "created" event.
     * Note: Most logic is in ReturnService
     * This observer handles any post-creation tasks
     */
    public function created(ReturnModel $return): void
    {
        AuditService::logCreate($return);
    }

    /**
     * Handle the Return "updated" event.
     * Handle cancellation
     */
    public function updated(ReturnModel $return): void
    {
        if ($return->wasChanged('status')) {
            $oldStatus = $return->getOriginal('status');
            $newStatus = $return->status;

            // Cancelling a return
            if ($oldStatus === 'active' && $newStatus === 'cancelled') {
                $this->handleCancellation($return);
            }
        }

        AuditService::logUpdate($return, $return->getOriginal());
    }

    /**
     * Handle return cancellation
     * 1. Decrease inventory back
     * 2. Increase customer balance back
     */
    private function handleCancellation(ReturnModel $return): void
    {
        // Decrease inventory for each item
        foreach ($return->items as $item) {
            $item->targetShipmentItem->decrement('remaining_quantity', (float) $item->quantity);
        }

        // Increase customer balance back
        Customer::where('id', $return->customer_id)
            ->increment('balance', (float) $return->total_amount);
    }
}
