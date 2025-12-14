<?php

namespace App\Observers;

use App\Models\ShipmentItem;
use App\Models\Shipment;

class ShipmentItemObserver
{
    /**
     * Handle the ShipmentItem "updated" event.
     * Auto-close shipment when all items sold
     */
    public function updated(ShipmentItem $item): void
    {
        // Check if this update could trigger auto-close
        if ($item->wasChanged('remaining_quantity') && $item->remaining_quantity <= 0) {
            $this->checkAutoClose($item->shipment);
        }
    }

    /**
     * Check if shipment should be auto-closed
     * Shipment closes when all items have zero remaining
     */
    private function checkAutoClose(Shipment $shipment): void
    {
        // Only check open shipments
        if ($shipment->status !== 'open') {
            return;
        }

        // Check if any item still has stock
        $hasStock = $shipment->items()
            ->where('remaining_quantity', '>', 0)
            ->exists();

        if (!$hasStock) {
            $shipment->status = 'closed';
            $shipment->saveQuietly();
        }
    }
}
