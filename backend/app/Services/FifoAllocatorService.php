<?php

namespace App\Services;

use App\Models\ShipmentItem;
use App\Models\InvoiceItem;
use App\Exceptions\BusinessException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FifoAllocatorService
{
    /**
     * Allocate quantity from available shipment items using FIFO
     * 
     * @param int $productId Product to allocate
     * @param float $quantity Quantity needed
     * @return Collection<int, array{shipment_item_id: int, quantity: float, shipment_number: string}>
     * @throws \Exception If insufficient stock
     */
    public function allocate(int $productId, float $quantity): Collection
    {
        $allocations = collect();
        $remaining = $quantity;

        // Get available shipment items (FIFO: by fifo_sequence)
        // Best Practice 2025-12-13: استخدام fifo_sequence بدلاً من date
        // - fifo_sequence: للقرارات المحاسبية (غير قابل للتعديل)
        // - date: للتقارير فقط
        $availableItems = ShipmentItem::query()
            ->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->orderBy('shipments.fifo_sequence', 'asc')
            ->orderBy('shipment_items.id', 'asc')
            ->select('shipment_items.*')
            ->with('shipment:id,number')
            ->lockForUpdate()
            ->get();

        foreach ($availableItems as $item) {
            if ($remaining <= 0)
                break;

            $take = min($remaining, $item->remaining_quantity);

            $allocations->push([
                'shipment_item_id' => $item->id,
                'quantity' => $take,
                'shipment_number' => $item->shipment->number,
                'weight_per_unit' => $item->weight_per_unit,
                'unit_cost' => $item->unit_cost,
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            $available = $quantity - $remaining;
            throw new BusinessException(
                'INV_005',
                "المخزون غير كافي. المطلوب: {$quantity}، المتاح: {$available}",
                "Insufficient stock. Required: {$quantity}, Available: {$available}"
            );
        }

        return $allocations;
    }

    /**
     * Execute FIFO allocation and update inventory
     * Returns created invoice items
     * 
     * @param int $invoiceId Invoice to allocate for
     * @param int $productId Product to allocate
     * @param float $quantity Total quantity
     * @param float $unitPrice Selling price per unit
     * @param int $cartons Number of cartons
     * @return Collection<int, InvoiceItem>
     */
    public function allocateAndCreate(
        int $invoiceId,
        int $productId,
        float $quantity,
        float $unitPrice,
        int $cartons = 0
    ): Collection {
        return DB::transaction(function () use ($invoiceId, $productId, $quantity, $unitPrice, $cartons) {
            $allocations = $this->allocate($productId, $quantity);
            $createdItems = collect();

            foreach ($allocations as $allocation) {
                // Update shipment item
                ShipmentItem::where('id', $allocation['shipment_item_id'])
                    ->update([
                        'remaining_quantity' => DB::raw("remaining_quantity - {$allocation['quantity']}"),
                        'sold_quantity' => DB::raw("sold_quantity + {$allocation['quantity']}"),
                    ]);

                // Create invoice item
                $item = InvoiceItem::create([
                    'invoice_id' => $invoiceId,
                    'product_id' => $productId,
                    'shipment_item_id' => $allocation['shipment_item_id'],
                    'cartons' => $cartons, // Will be distributed proportionally if needed
                    'quantity' => $allocation['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $allocation['quantity'] * $unitPrice,
                ]);

                $createdItems->push($item);
            }

            return $createdItems;
        });
    }

    /**
     * Reverse allocation (for invoice cancellation)
     * 
     * @param int $invoiceItemId Invoice item to reverse
     */
    public function reverseAllocation(int $invoiceItemId): void
    {
        $item = InvoiceItem::findOrFail($invoiceItemId);

        ShipmentItem::where('id', $item->shipment_item_id)
            ->update([
                'remaining_quantity' => DB::raw("remaining_quantity + {$item->quantity}"),
                'sold_quantity' => DB::raw("sold_quantity - {$item->quantity}"),
            ]);
    }

    /**
     * Check available stock for a product
     * 
     * @param int $productId
     * @return float
     */
    public function getAvailableStock(int $productId): float
    {
        return ShipmentItem::query()
            ->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->sum('remaining_quantity');
    }

    /**
     * Get FIFO breakdown for a product
     * 
     * @param int $productId
     * @return Collection
     */
    public function getFifoBreakdown(int $productId): Collection
    {
        // تصحيح 2025-12-13: ترتيب حسب shipment.date
        return ShipmentItem::query()
            ->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->orderBy('shipments.fifo_sequence', 'asc')
            ->orderBy('shipment_items.id', 'asc')
            ->select('shipment_items.*')
            ->with('shipment:id,number,date')
            ->get(['shipment_items.id', 'shipment_items.shipment_id', 'shipment_items.weight_per_unit', 'shipment_items.remaining_quantity', 'shipment_items.unit_cost', 'shipment_items.created_at']);
    }
}
