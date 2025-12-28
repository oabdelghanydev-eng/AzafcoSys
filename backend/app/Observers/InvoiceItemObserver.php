<?php

namespace App\Observers;

use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Log;

/**
 * InvoiceItemObserver
 * 
 * SAFETY GUARD: Detects potential bypasses of FifoAllocatorService.
 * 
 * The FifoAllocatorService is the ONLY authorized path for creating InvoiceItems
 * because it properly updates ShipmentItem.sold_cartons (inventory).
 * 
 * This observer does NOT block creation - it only logs warnings for investigation.
 * This prevents breaking seeds, tests, or migrations while providing visibility.
 */
class InvoiceItemObserver
{
    /**
     * Track if creation is via FifoAllocatorService
     */
    private static bool $viaFifoService = false;

    /**
     * Call this from FifoAllocatorService before creating items
     */
    public static function markViaFifoService(): void
    {
        self::$viaFifoService = true;
    }

    /**
     * Reset the flag after creation
     */
    public static function resetFifoFlag(): void
    {
        self::$viaFifoService = false;
    }

    /**
     * Handle the InvoiceItem "created" event.
     * 
     * Logs warning if creation bypassed FifoAllocatorService.
     * This helps identify:
     * - Seeders that don't update inventory
     * - Tests that create ghost allocations
     * - Future code that bypasses FIFO
     */
    public function created(InvoiceItem $item): void
    {
        if (!self::$viaFifoService) {
            Log::warning('[InvoiceItemObserver] InvoiceItem created outside FifoAllocatorService', [
                'invoice_item_id' => $item->id,
                'invoice_id' => $item->invoice_id,
                'product_id' => $item->product_id,
                'cartons' => $item->cartons,
                'shipment_item_id' => $item->shipment_item_id,
                'warning' => 'Inventory (sold_cartons) may not be updated!',
            ]);
        }
    }
}
