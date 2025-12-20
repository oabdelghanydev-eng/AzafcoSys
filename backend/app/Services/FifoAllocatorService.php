<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\InvoiceItem;
use App\Models\ShipmentItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FifoAllocatorService
{
    /**
     * Allocate cartons from available shipment items using FIFO
     *
     * @param  int  $productId  Product to allocate
     * @param  int  $cartons  Number of cartons needed
     * @return Collection<int, array{shipment_item_id: int, cartons: int, weight_per_unit: float}>
     *
     * @throws BusinessException If insufficient stock
     */
    public function allocate(int $productId, int $cartons): Collection
    {
        $allocations = collect();
        $remaining = $cartons;

        // Get available shipment items (FIFO: by fifo_sequence)
        $availableItems = ShipmentItem::query()
            ->where('product_id', $productId)
            ->whereRaw('(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) > 0')
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->orderBy('shipments.fifo_sequence', 'asc')
            ->orderBy('shipment_items.id', 'asc')
            ->select('shipment_items.*')
            ->with('shipment:id,number')
            ->lockForUpdate()
            ->get();

        foreach ($availableItems as $item) {
            if ($remaining <= 0) {
                break;
            }

            $available = $item->remaining_cartons;
            $take = min($remaining, $available);

            $allocations->push([
                'shipment_item_id' => $item->id,
                'cartons' => $take,
                'shipment_number' => $item->shipment->number,
                'weight_per_unit' => $item->weight_per_unit,
                'unit_cost' => $item->unit_cost,
            ]);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            $availableStock = $cartons - $remaining;
            throw new BusinessException(
                'INV_005',
                "الكراتين المطلوبة غير متوفرة في المخزون. مطلوب: {$cartons}، متوفر: {$availableStock}",
                "Requested cartons not available in stock. Requested: {$cartons}, Available: {$availableStock}"
            );
        }

        return $allocations;
    }

    /**
     * Execute FIFO allocation and update inventory
     * Returns created invoice items
     *
     * @param  int  $invoiceId  Invoice to allocate for
     * @param  int  $productId  Product to allocate
     * @param  int  $cartons  Number of cartons to sell
     * @param  float  $totalWeight  Actual weight from scale (kg)
     * @param  float  $unitPrice  Selling price per kg
     * @return Collection<int, InvoiceItem>
     */
    public function allocateAndCreate(
        int $invoiceId,
        int $productId,
        int $cartons,
        float $totalWeight,
        float $unitPrice
    ): Collection {
        return DB::transaction(function () use ($invoiceId, $productId, $cartons, $totalWeight, $unitPrice) {
            $allocations = $this->allocate($productId, $cartons);
            $createdItems = collect();

            $totalAllocatedCartons = $allocations->sum('cartons');
            $remainingWeight = $totalWeight;

            foreach ($allocations as $index => $allocation) {
                // Update shipment item - increment sold_cartons
                ShipmentItem::where('id', $allocation['shipment_item_id'])
                    ->increment('sold_cartons', $allocation['cartons']);

                // Distribute weight proportionally across allocations
                $isLast = ($index === $allocations->count() - 1);
                if ($isLast) {
                    // Last allocation gets remaining weight to avoid rounding issues
                    $allocatedWeight = $remainingWeight;
                } else {
                    // Proportional weight: (cartons / totalCartons) * totalWeight
                    $allocatedWeight = round(
                        ($allocation['cartons'] / $totalAllocatedCartons) * $totalWeight,
                        3
                    );
                    $remainingWeight -= $allocatedWeight;
                }

                // Create invoice item
                $item = InvoiceItem::create([
                    'invoice_id' => $invoiceId,
                    'product_id' => $productId,
                    'shipment_item_id' => $allocation['shipment_item_id'],
                    'cartons' => $allocation['cartons'],
                    'quantity' => $allocatedWeight,  // actual weight from scale
                    'unit_price' => $unitPrice,
                    'subtotal' => $allocatedWeight * $unitPrice,
                ]);

                $createdItems->push($item);
            }

            return $createdItems;
        });
    }

    /**
     * Reverse allocation (for invoice cancellation)
     *
     * @param  int  $invoiceItemId  Invoice item to reverse
     */
    public function reverseAllocation(int $invoiceItemId): void
    {
        $item = InvoiceItem::findOrFail($invoiceItemId);

        ShipmentItem::where('id', $item->shipment_item_id)
            ->decrement('sold_cartons', $item->cartons);
    }

    /**
     * Check available cartons stock for a product
     */
    public function getAvailableStock(int $productId): int
    {
        return (int) ShipmentItem::query()
            ->where('product_id', $productId)
            ->whereRaw('(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) > 0')
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->selectRaw('SUM(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) as total')
            ->value('total') ?? 0;
    }

    /**
     * Get FIFO breakdown for a product
     */
    public function getFifoBreakdown(int $productId): Collection
    {
        return ShipmentItem::query()
            ->where('product_id', $productId)
            ->whereRaw('(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) > 0')
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->orderBy('shipments.fifo_sequence', 'asc')
            ->orderBy('shipment_items.id', 'asc')
            ->select('shipment_items.*')
            ->with('shipment:id,number,date')
            ->get();
    }
}

