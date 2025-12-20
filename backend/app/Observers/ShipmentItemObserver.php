<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Models\ShipmentItem;

class ShipmentItemObserver
{
    /**
     * Handle the ShipmentItem "updated" event.
     * Auto-close shipment when all items sold
     */
    public function updated(ShipmentItem $item): void
    {
        // Check if this update could trigger auto-close (sold_cartons changed)
        if ($item->wasChanged('sold_cartons') && $item->remaining_cartons <= 0) {
            $this->checkAutoClose($item->shipment);
        }
    }

    /**
     * Check if shipment should be auto-closed
     * Shipment closes when all items have zero remaining cartons
     */
    private function checkAutoClose(Shipment $shipment): void
    {
        // Only check open shipments
        if ($shipment->status !== 'open') {
            return;
        }

        // Check if any item still has stock (using accessor)
        $hasStock = $shipment->items->contains(fn($item) => $item->remaining_cartons > 0);

        if (!$hasStock) {
            $shipment->status = 'closed';
            $shipment->saveQuietly();
        }
    }
}

