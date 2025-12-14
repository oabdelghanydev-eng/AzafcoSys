<?php

namespace App\Observers;

use App\Models\CollectionAllocation;
use App\Models\Invoice;

class CollectionAllocationObserver
{
    /**
     * Handle the CollectionAllocation "created" event.
     * Increases invoice paid_amount and decreases balance
     */
    public function created(CollectionAllocation $allocation): void
    {
        Invoice::where('id', $allocation->invoice_id)
            ->update([
                'paid_amount' => \DB::raw("paid_amount + {$allocation->amount}"),
                'balance' => \DB::raw("balance - {$allocation->amount}"),
            ]);
    }

    /**
     * Handle the CollectionAllocation "deleted" event.
     * Decreases invoice paid_amount and increases balance
     */
    public function deleted(CollectionAllocation $allocation): void
    {
        // Only update if the invoice still exists and is active
        Invoice::where('id', $allocation->invoice_id)
            ->where('status', 'active')
            ->update([
                'paid_amount' => \DB::raw("paid_amount - {$allocation->amount}"),
                'balance' => \DB::raw("balance + {$allocation->amount}"),
            ]);

        // Recalculate collection's allocated/unallocated amounts
        $allocation->collection?->recalculateAllocations();
    }
}
