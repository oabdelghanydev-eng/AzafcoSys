# Shipment Business Logic

## 📋 Overview

Shipments are the **Main Source of Inventory** in the system.
Each shipment comes from a supplier and contains multiple items.

---

## 🔄 Shipment Lifecycle

```
┌─────────────┐
│    open     │ ← Default State on Creation
└──────┬──────┘
       │
       │ All quantities sold
       ▼
┌─────────────┐
│   closed    │ ← Shipment Depleted (Automatic)
└──────┬──────┘
       │
       │ Settle + Carry Over Remaining
       ▼
┌─────────────┐
│  settled    │ ← Final Settlement
└──────┬──────┘
       │
       │ Unsettle (Rare)
       ▼
┌─────────────┐
│   closed    │ ← Reopen
└─────────────┘
```

---

## 📊 Decision Table: Status Transition

| From | To | Condition | Mechanism |
|----|-----|-------|--------|
| open | closed | remaining = 0 | Automatic (Observer) |
| open | settled | Manually | settle() |
| closed | settled | Manually | settle() |
| settled | closed | Unsettle + safety check | unsettle() |
| open | open | — | No Change |

---

## 🧮 Calculations

### 1. Total Remaining Calculation

```
total_remaining = SUM(shipment_items.remaining_quantity)

If total_remaining = 0:
  status → 'closed'
```

### 2. Shipment Settlement Calculation

```
For each item in shipment:
┌────────────────────────────────────────────────┐
│ initial_quantity    = Original Qty             │
│ sold_quantity       = Sold Qty                 │
│ wastage_quantity    = Wastage Qty              │
│ returned_quantity   = Returned Qty             │
│ carryover_in        = Carried In               │
│ carryover_out       = Carried Out              │
│ remaining_quantity  = Remaining                │
│                                               │
│ Check:                                         │
│ initial + carryover_in - sold - wastage       │
│ - carryover_out = remaining                   │
└────────────────────────────────────────────────┘
```

### 3. Carryover

```
When settling a shipment with remaining items:

FOR EACH item WITH remaining > 0:
  CREATE Carryover(
    from_shipment = current,
    to_shipment = next_open_shipment,
    quantity = item.remaining,
    reason = 'end_of_shipment'
  )
  
  CREATE ShipmentItem in next_shipment(
    initial_quantity = carryover.quantity,
    remaining_quantity = carryover.quantity
  )
  
  item.remaining_quantity = 0
  item.carryover_out_quantity = carryover.quantity
```

---

## 📝 Validation Rules

### CreateShipmentRequest

```php
[
    'supplier_id' => 'required|exists:suppliers,id',
    'number' => 'required|unique:shipments,number',  // OR auto-generated
    'date' => 'required|date',
    'arrival_date' => 'nullable|date|after_or_equal:date',
    'items' => 'required|array|min:1',
    'items.*.product_id' => 'required|exists:products,id',
    'items.*.cartons' => 'required|integer|min:1',
    'items.*.weight_per_unit' => 'required|numeric|min:0.001',
    // 'items.*.initial_quantity' => auto-calculated by backend = cartons × weight_per_unit
    'items.*.weight_label' => 'nullable|string|max:50',
    'items.*.unit_cost' => 'nullable|numeric|min:0', // defaults to 0
    'notes' => 'nullable|string|max:1000',
]

// Backend auto-calculates:
// initial_quantity = cartons × weight_per_unit
// remaining_quantity = initial_quantity
```

### SettleShipmentRequest

```php
[
    'next_shipment_id' => 'required|exists:shipments,id',
    // Next shipment must be open
    // Custom validation
]
```

### UpdateShipmentRequest (2025-12-16)

```php
// ✅ Update Rules:
// - Only open shipments can be edited
// - Cannot reduce quantity below sold amount

[
    'date' => 'sometimes|date',
    'notes' => 'nullable|string|max:1000',
    'items' => 'sometimes|array',
    'items.*.id' => 'required|exists:shipment_items,id',
    'items.*.weight_per_unit' => 'sometimes|numeric|min:0.001',
    'items.*.initial_quantity' => 'sometimes|numeric|min:0.001',
]

// Controller Validation:
// - status !== 'open' → SHP_009
// - initial_quantity < sold_quantity → SHP_010
```

---

## 🔐 Authorization Rules (Policies)

### ShipmentPolicy

```php
class ShipmentPolicy
{
    public function view(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('view_shipments');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermission('create_shipments');
    }
    
    public function update(User $user, Shipment $shipment): bool
    {
        if (!$user->hasPermission('edit_shipments')) {
            return false;
        }
        
        // Cannot edit settled shipment (except status)
        if ($shipment->status === 'settled') {
            return false;
        }
        
        return true;
    }
    
    public function settle(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('settle_shipments')
            && $shipment->status !== 'settled';
    }
    
    public function unsettle(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('unsettle_shipments')
            && $shipment->status === 'settled';
    }
    
    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('delete_shipments')
            && !$shipment->hasInvoices();
    }
}
```

---

## 🔄 Observer Logic

### ShipmentItemObserver

```php
class ShipmentItemObserver
{
    /**
     * EVENT: updated
     * PURPOSE: Automatically close shipment when depleted
     */
    public function updated(ShipmentItem $item): void
    {
        // Only when remaining_quantity changes
        if (!$item->wasChanged('remaining_quantity')) {
            return;
        }
        
        // Only when it becomes zero
        if ($item->remaining_quantity != 0) {
            return;
        }
        
        // Calculate total remaining in shipment
        $totalRemaining = $item->shipment
            ->items()
            ->sum('remaining_quantity');
        
        // If shipment is completely empty
        if ($totalRemaining == 0) {
            $item->shipment->update([
                'status' => 'closed',
                'closed_at' => now()
            ]);
        }
    }
}
```

### ShipmentObserver

```php
class ShipmentObserver
{
    /**
     * EVENT: deleting
     * PURPOSE: Prevent deleting shipment with invoices
     */
    public function deleting(Shipment $shipment): bool
    {
        $hasInvoices = InvoiceItem::whereIn(
            'shipment_item_id',
            $shipment->items->pluck('id')
        )->exists();
        
        if ($hasInvoices) {
            throw new \Exception("Cannot delete shipment with linked invoices");
        }
        
        return true;
    }
    
    /**
     * EVENT: updating
     * PURPOSE: Protect settled shipment
     */
    public function updating(Shipment $shipment): bool
    {
        if ($shipment->getOriginal('status') === 'settled') {
            // Allow changing status only
            $changedFields = array_keys($shipment->getDirty());
            $allowedFields = ['status', 'updated_at'];
            
            $forbidden = array_diff($changedFields, $allowedFields);
            
            if (!empty($forbidden)) {
                throw new \Exception(
                    "Cannot edit settled shipment: " . implode(', ', $forbidden)
                );
            }
        }
        
        return true;
    }
    
    /**
     * EVENT: updated
     * PURPOSE: Handle Unsettle
     */
    public function updated(Shipment $shipment): void
    {
        $oldStatus = $shipment->getOriginal('status');
        $newStatus = $shipment->status;
        
        // Unsettle: settled → closed/open
        if ($oldStatus === 'settled' && $newStatus !== 'settled') {
            $this->reverseCarryovers($shipment);
        }
    }
    
    /**
     * Reverse Carryovers
     */
    private function reverseCarryovers(Shipment $shipment): void
    {
        DB::transaction(function () use ($shipment) {
            $carryovers = Carryover::where('from_shipment_id', $shipment->id)
                ->where('reason', 'end_of_shipment')
                ->with(['fromShipmentItem', 'toShipmentItem', 'toShipment'])
                ->get();
            
            foreach ($carryovers as $carryover) {
                $nextItem = $carryover->toShipmentItem;
                
                // Safety Check
                if ($nextItem->remaining_quantity < $carryover->quantity) {
                    throw new \Exception(
                        "Cannot unsetttle! " .
                        "Carried quantity has been sold from next shipment"
                    );
                }
                
                // Restore to original shipment
                $carryover->fromShipmentItem->increment(
                    'remaining_quantity',
                    $carryover->quantity
                );
                
                // Deduct from next shipment
                $nextItem->decrement('initial_quantity', $carryover->quantity);
                $nextItem->decrement('remaining_quantity', $carryover->quantity);
                
                // Delete item if empty
                if ($nextItem->initial_quantity <= 0) {
                    $nextItem->delete();
                }
                
                // Delete carryover record
                $carryover->delete();
            }
            
            $shipment->settled_at = null;
            $shipment->saveQuietly();
        });
    }
}
```

---

## 🛠️ ShipmentService

```php
<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Carryover;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    /**
     * Settle Shipment
     */
    public function settle(Shipment $shipment, Shipment $nextShipment): void
    {
        if ($shipment->status === 'settled') {
            throw new \Exception("Shipment already settled");
        }
        
        if ($nextShipment->status !== 'open') {
            throw new \Exception("Next Shipment must be open");
        }
        
        DB::transaction(function () use ($shipment, $nextShipment) {
            // Get items with remaining quantity
            $itemsWithRemaining = $shipment->items()
                ->where('remaining_quantity', '>', 0)
                ->get();
            
            foreach ($itemsWithRemaining as $item) {
                // Create item in next shipment
                $newItem = ShipmentItem::create([
                    'shipment_id' => $nextShipment->id,
                    'product_id' => $item->product_id,
                    'weight_label' => $item->weight_label,
                    'weight_per_unit' => $item->weight_per_unit,
                    'cartons' => $item->cartons, // Approximation
                    'initial_quantity' => $item->remaining_quantity,
                    'remaining_quantity' => $item->remaining_quantity,
                    'carryover_in_quantity' => $item->remaining_quantity,
                ]);
                
                // Create Carryover Record
                Carryover::create([
                    'from_shipment_id' => $shipment->id,
                    'from_shipment_item_id' => $item->id,
                    'to_shipment_id' => $nextShipment->id,
                    'to_shipment_item_id' => $newItem->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->remaining_quantity,
                    'cartons' => $item->cartons,
                    'weight_per_unit' => $item->weight_per_unit,
                    'reason' => 'end_of_shipment',
                    'created_by' => auth()->id(),
                ]);
                
                // Update original item
                $item->update([
                    'carryover_out_quantity' => $item->remaining_quantity,
                    'remaining_quantity' => 0,
                ]);
            }
            
            // Change Shipment Status
            $shipment->update([
                'status' => 'settled',
                'settled_at' => now(),
            ]);
        });
    }
    
    /**
     * Generate Settlement Report
     */
    public function generateSettlementReport(Shipment $shipment): array
    {
        $items = $shipment->items()->with('product')->get();
        
        $report = [
            'shipment' => $shipment,
            'items' => [],
            'totals' => [
                'initial' => 0,
                'sold' => 0,
                'wastage' => 0,
                'returned' => 0,
                'carryover_in' => 0,
                'carryover_out' => 0,
                'remaining' => 0,
            ],
        ];
        
        foreach ($items as $item) {
            $report['items'][] = [
                'product' => $item->product->name,
                'initial' => $item->initial_quantity,
                'sold' => $item->sold_quantity,
                'wastage' => $item->wastage_quantity,
                'returned' => $item->returned_quantity,
                'carryover_in' => $item->carryover_in_quantity,
                'carryover_out' => $item->carryover_out_quantity,
                'remaining' => $item->remaining_quantity,
            ];
            
            $report['totals']['initial'] += $item->initial_quantity;
            $report['totals']['sold'] += $item->sold_quantity;
            // ... etc
        }
        
        return $report;
    }
}
```

---

## 📊 Flowchart: Shipment Settlement

```
┌─────────────┐
│    Start    │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│ shipment.status ≠       │
│ 'settled'?              │
└───────────┬─────────────┘
            │
    ┌───────┴───────┐
    │               │
   ✅              ❌
    │               │
    ▼               ▼
┌─────────┐    ┌──────────────────┐
│ Proceed  │    │"Already Settled"  │
└────┬────┘    └──────────────────┘
     │
     ▼
┌─────────────────────────┐
│ next_shipment.status    │
│ = 'open'?               │
└───────────┬─────────────┘
            │
    ┌───────┴───────┐
    │               │
   ✅              ❌
    │               │
    ▼               ▼
┌─────────┐    ┌──────────────────────┐
│ Proceed  │    │ "Must Choose Open   │
└────┬────┘    │  Shipment"           │
     │         └──────────────────────┘
     ▼
┌─────────────────────────┐
│ DB::transaction START   │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ GET items WHERE         │
│ remaining > 0           │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ FOREACH item:           │
│ - CREATE new item       │
│ - CREATE carryover      │
│ - UPDATE original item  │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ shipment.status =       │
│ 'settled'               │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ COMMIT                  │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ Return Settlement Report│
└─────────────────────────┘
```

---

## ⚠️ Edge Cases

### 1. Settling Empty Shipment

```
Case: All remaining = 0

Result:
  - No carryover
  - status → 'settled'
  - Report without carryovers
```

### 2. Unsettle after selling carried quantity

```
Case:
  - Carried 100 kg to next shipment
  - Next shipment sold 60

Attempt: Unsettle

Check:
  nextItem.remaining (40) < carryover.quantity (100)

Result:
  throw Exception("Cannot unsettle")
```

### 3. Deleting Shipment with Incoming Carryovers

```
Case:
  - Shipment B has carryover_in from Shipment A
  - Attempt to delete Shipment B

Result:
  - Check InvoiceItems
  - If no invoices: Deletion allowed
  - Carryover will be deleted (CASCADE)
```

### 4. Post-Settlement Return

```
Case:
  - Shipment Settled
  - Customer returns goods from this shipment

Handling: (late_return)
  - CREATE Carryover(reason = 'late_return')
  - Add to Current Open Shipment
  - Do NOT reopen settled shipment
```

---

## 📈 Performance Considerations

### Indexes

```sql
-- shipments
INDEX idx_supplier (supplier_id)
INDEX idx_date (date)
INDEX idx_status (status)

-- shipment_items
INDEX idx_shipment (shipment_id)
INDEX idx_product (product_id)
INDEX idx_remaining (remaining_quantity)
INDEX idx_product_remaining (product_id, remaining_quantity) -- For FIFO
```

---

## 🔗 Related Rules

- BR-SHP-001 to BR-SHP-007
- BR-FIFO-001 to BR-FIFO-003
