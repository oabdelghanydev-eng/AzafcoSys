# Inventory FIFO Business Logic

## 📋 Overview

FIFO = **F**irst **I**n, **F**irst **O**ut
Selling from the oldest shipment first to ensure inventory turnover.

---

## 🔄 How FIFO Works?

```
Inventory:
┌──────────────────────────────────────────────┐
│ Shipment #1 (10 Dec) - 50 kg remaining       │ ← Oldest
│ Shipment #2 (15 Dec) - 100 kg remaining      │
│ Shipment #3 (20 Dec) - 75 kg remaining       │ ← Newest
└──────────────────────────────────────────────┘

Sales Request: 80 kg

FIFO Allocation:
┌──────────────────────────────────────────────┐
│ From Shipment #1: 50 kg (Depleted)           │
│ From Shipment #2: 30 kg (Remaining 70)       │
└──────────────────────────────────────────────┘

Result:
  invoice_items: 2 items
  - item 1: shipment_item_id=#1, qty=50
  - item 2: shipment_item_id=#2, qty=30
```

---

## 🧮 Algorithm

### FifoAllocatorService

```php
<?php

namespace App\Services;

use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;

class FifoAllocatorService
{
    /**
     * Allocate inventory quantity for a specific product
     * 
     * @param int $productId The product
     * @param float $quantity Required quantity
     * @return array Array of allocations
     * @throws \Exception If quantity is not available
     */
    public function allocate(int $productId, float $quantity): array
    {
        return DB::transaction(function () use ($productId, $quantity) {
            $remaining = $quantity;
            $allocations = [];
            
            // Fetch available items (Oldest first by fifo_sequence)
            // Best Practice 2025-12-13:
            // - fifo_sequence: For accounting decisions (Immutable)
            // - date: For reports only
            $availableItems = ShipmentItem::where('product_id', $productId)
                ->where('remaining_quantity', '>', 0)
                ->whereHas('shipment', function ($q) {
                    $q->whereIn('status', ['open', 'closed']);
                })
                ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
                ->orderBy('shipments.fifo_sequence', 'asc') // FIFO: By sequence
                ->orderBy('shipment_items.id', 'asc')
                ->select('shipment_items.*')
                ->lockForUpdate() // Race Condition Protection
                ->get();
            
            foreach ($availableItems as $item) {
                if ($remaining <= 0) break;
                
                $allocateQty = min($remaining, $item->remaining_quantity);
                
                $allocations[] = [
                    'shipment_item_id' => $item->id,
                    'shipment_id' => $item->shipment_id,
                    'quantity' => $allocateQty,
                    'unit_cost' => $this->calculateUnitCost($item),
                ];
                
                // Update remaining
                $item->decrement('remaining_quantity', $allocateQty);
                $item->increment('sold_quantity', $allocateQty);
                
                $remaining -= $allocateQty;
            }
            
            // Check Availability
            if ($remaining > 0) {
                throw new \Exception(
                    "Required quantity ({$quantity}) not available. " .
                    "Available: " . ($quantity - $remaining)
                );
            }
            
            return $allocations;
        });
    }
    
    /**
     * Check quantity availability (without deduction)
     */
    public function checkAvailability(int $productId, float $quantity): bool
    {
        $available = ShipmentItem::where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->whereHas('shipment', function ($q) {
                $q->where('status', '!=', 'settled');
            })
            ->sum('remaining_quantity');
        
        return $available >= $quantity;
    }
    
    /**
     * Get available quantity
     */
    public function getAvailableQuantity(int $productId): float
    {
        return ShipmentItem::where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->whereHas('shipment', function ($q) {
                $q->where('status', '!=', 'settled');
            })
            ->sum('remaining_quantity');
    }
    
    /**
     * Calculate Unit Cost
     */
    private function calculateUnitCost(ShipmentItem $item): float
    {
        // Can be calculated from Shipment Price / Quantity
        return 0; // Simplified for now
    }
    
    /**
     * Deallocate (On invoice cancel/delete)
     */
    public function deallocate(array $allocations): void
    {
        DB::transaction(function () use ($allocations) {
            foreach ($allocations as $allocation) {
                $item = ShipmentItem::find($allocation['shipment_item_id']);
                
                if ($item) {
                    $item->increment('remaining_quantity', $allocation['quantity']);
                    $item->decrement('sold_quantity', $allocation['quantity']);
                }
            }
        });
    }
}
```

---

## 📊 Decision Table: FIFO Allocation

| Case | Condition | Result |
|--------|-------|---------|
| Quantity available in single shipment | required ≤ item.remaining | Single allocation |
| Quantity requires multiple shipments | required > item.remaining | Multiple allocations |
| Quantity not available | SUM(remaining) < required | Exception |
| Product does not exist | product_id invalid | Exception |
| Settled Shipment | status = 'settled' | Skipped |

---

## 🔄 Flowchart: FIFO Allocation

```
┌─────────────┐
│    Start    │
│ (product,   │
│  quantity)  │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│ GET available items     │
│ WHERE remaining > 0     │
│ ORDER BY date ASC       │
│ lockForUpdate()         │
└───────────┬─────────────┘
       │
       ▼
┌─────────────────────────┐
│ remaining = quantity    │
│ allocations = []        │
└───────────┬─────────────┘
       │
       ▼
┌─────────────────────────┐
│ FOREACH item            │
└───────────┬─────────────┘
       │
       ▼
   ┌───────────────┐
   │remaining > 0? │
   └───────┬───────┘
           │
   ┌───────┴───────┐
   │               │
  Yes              No
   │               │
   ▼               ▼
┌──────────┐   ┌──────────┐
│ allocate │   │  BREAK   │
│ = MIN()  │   └──────────┘
└────┬─────┘
     │
     ▼
┌─────────────────────────┐
│ item.remaining -=       │
│ item.sold +=            │
│ remaining -=            │
│ allocations.push()      │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ NEXT item               │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ remaining > 0?          │
└───────────┬─────────────┘
            │
    ┌───────┴───────┐
    │               │
   Yes              No
    │               │
    ▼               ▼
┌──────────────┐ ┌──────────────┐
│   Exception  │ │   RETURN     │
│"Not Available"| │ allocations  │
└──────────────┘ └──────────────┘
```

---

## ⚠️ Edge Cases

### 1. Selling More than Available

```
Available: 50 kg
Required: 80 kg

Result: Exception
Message: "Required quantity (80) not available. Available: 50"
```

### 2. Shipment Depleted During Sale

```
Case:
  - Shipment #1: 10 kg
  - Shipment #2: 100 kg
  - Required: 15 kg

FIFO:
  - 10 from #1 (Depleted)
  - 5 from #2

ShipmentItemObserver:
  - Detects #1 remaining = 0
  - Checks shipment: Is it fully depleted?
  - If yes: status → 'closed'
```

### 3. Race Condition

```
Problem:
  - User A: Sells 50 from inventory
  - User B: Sells 30 from same inventory
  - Same moment

Protection:
  lockForUpdate() on ShipmentItems

Result:
  - User A locks rows
  - User B waits
  - User A completes
  - User B gets updated data
```

### 4. Invoice Cancellation

```
Case:
  - Invoice with 2 items from different shipments
  - item 1: 30 kg from shipment #1
  - item 2: 20 kg from shipment #2

On Cancel:
  FifoAllocatorService::deallocate([
    ['shipment_item_id' => 1, 'quantity' => 30],
    ['shipment_item_id' => 2, 'quantity' => 20],
  ])

Result:
  - shipment_item #1: remaining += 30, sold -= 30
  - shipment_item #2: remaining += 20, sold -= 20
```

---

## 📈 Inventory Tracking

### Current Inventory Report

```php
public function getInventoryReport(): array
{
    return Product::with(['shipmentItems' => function ($q) {
        $q->where('remaining_quantity', '>', 0)
          ->whereHas('shipment', fn($s) => $s->where('status', '!=', 'settled'));
    }])
    ->get()
    ->map(function ($product) {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'available_quantity' => $product->shipmentItems->sum('remaining_quantity'),
            'by_shipment' => $product->shipmentItems->map(fn($i) => [
                'shipment_id' => $i->shipment_id,
                'remaining' => $i->remaining_quantity,
            ]),
        ];
    });
}
```

---

## 🔗 Related Rules

- BR-FIFO-001: Quantity Allocation
- BR-FIFO-002: Source Tracking
- BR-FIFO-003: Remaining Update
