# Refunds & Credit Notes

## 📋 Overview

This file documents how to handle refunds in the system.

---

## 🔄 Refund Types

| Type | Description | Impact |
|-------|-------|---------|
| **Pre-Settlement Refund** | Goods return to original shipment | `remaining_quantity` increases |
| **Post-Settlement Refund (Late Return)** | Goods transfer to open shipment | New `Carryover` |
| **Credit Note** | Reduce invoice value | `Credit Note` |

---

## 📊 Scenario 1: Pre-Settlement Refund

### Original State:
```
Shipment #1 (open):
- Item X: initial=100, sold=30, remaining=70

Invoice #5:
- 30 units of Item X at price 10
- total=300, paid=0, balance=300
```

### On Invoice Cancellation:
```
1. invoice.status = 'cancelled'
2. invoice.allocations.delete()
3. shipment_item.remaining_quantity += 30 (Returns to 100)
4. customer.balance -= 300
```

### Code:
```php
// On Invoice Cancel
DB::transaction(function () use ($invoice) {
    // Return quantities to inventory
    foreach ($invoice->items as $item) {
        $item->shipmentItem->increment('remaining_quantity', $item->quantity);
        $item->shipmentItem->decrement('sold_quantity', $item->quantity);
    }
    
    // Rest of cancel logic...
});
```

---

## 📊 Scenario 2: Post-Settlement Refund (Late Return)

### Case:
```
Shipment #1 (settled): - Settled
Shipment #2 (open): - Current Shipment

Customer returns 20 units of Item X (Originally from Shipment #1)
```

### Logic:
```
1. Do not reopen Settled Shipment
2. Create Carryover of type 'late_return'
3. Add quantity to Current Open Shipment
```

### Code:
```php
class ReturnService
{
    public function processLateReturn(
        Invoice $invoice,
        InvoiceItem $item,
        float $returnQuantity
    ): void {
        DB::transaction(function () use ($invoice, $item, $returnQuantity) {
            $originalShipmentItem = $item->shipmentItem;
            $originalShipment = $originalShipmentItem->shipment;
            
            // Find Current Open Shipment
            $currentOpenShipment = Shipment::where('status', 'open')
                ->orderBy('date', 'desc')
                ->first();
            
            if (!$currentOpenShipment) {
                throw new \Exception("No open shipment to receive return");
            }
            
            // Find existing item or create new
            $targetItem = $currentOpenShipment->items()
                ->where('product_id', $item->product_id)
                ->where('weight_per_unit', $item->shipmentItem->weight_per_unit)
                ->first();
            
            if ($targetItem) {
                $targetItem->increment('remaining_quantity', $returnQuantity);
                $targetItem->increment('carryover_in_quantity', $returnQuantity);
            } else {
                $targetItem = ShipmentItem::create([
                    'shipment_id' => $currentOpenShipment->id,
                    'product_id' => $item->product_id,
                    'weight_per_unit' => $item->shipmentItem->weight_per_unit,
                    'weight_label' => $item->shipmentItem->weight_label,
                    'initial_quantity' => $returnQuantity,
                    'remaining_quantity' => $returnQuantity,
                    'carryover_in_quantity' => $returnQuantity,
                ]);
            }
            
            // Create Carryover Record
            Carryover::create([
                'from_shipment_id' => $originalShipment->id,
                'from_shipment_item_id' => $originalShipmentItem->id,
                'to_shipment_id' => $currentOpenShipment->id,
                'to_shipment_item_id' => $targetItem->id,
                'product_id' => $item->product_id,
                'quantity' => $returnQuantity,
                'reason' => 'late_return',
                'notes' => "Return from Invoice #{$invoice->invoice_number}",
                'created_by' => auth()->id(),
            ]);
            
            // Create Credit Note
            $this->createCreditNote($invoice, $item, $returnQuantity);
        });
    }
}
```

---

## 📄 Credit Note

### Purpose:
Reduce value of existing invoice or create credit balance for customer.

### credit_notes Table (Future Proposal):
```sql
CREATE TABLE credit_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    credit_note_number VARCHAR(50) NOT NULL UNIQUE,
    
    customer_id BIGINT UNSIGNED NOT NULL,
    original_invoice_id BIGINT UNSIGNED NULL,
    
    reason ENUM('return', 'price_adjustment', 'damage', 'other') NOT NULL,
    
    amount DECIMAL(15,2) NOT NULL,
    date DATE NOT NULL,
    
    notes TEXT NULL,
    
    status ENUM('active', 'applied', 'cancelled') DEFAULT 'active',
    applied_to_invoice_id BIGINT UNSIGNED NULL,
    applied_at TIMESTAMP NULL,
    
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (original_invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (applied_to_invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔄 MVP Approach (No Credit Notes)

In MVP, we handle returns as follows:

### Option 1: Cancel Invoice and Create New One
```
1. Cancel original invoice
2. Return quantities to inventory
3. Create new invoice with correct quantities
```

### Option 2: Edit Invoice (In Edit Window)
```
1. Reduce quantity in invoice item
2. Observer updates inventory
3. Observer updates customer balance
```

---

## 📊 Decision Table: Return Type

| Case | Shipment | Action |
|--------|--------|---------|
| Invoice in Edit Window | Any | Edit Invoice |
| Invoice out of Window | open | Cancel + Return to Inventory |
| Invoice out of Window | closed | Cancel + Return to Inventory |
| Invoice out of Window | settled | Late Return + Carryover |

---

## 🔗 Related Business Rules

- BR-INV-003: Invoice Cancellation
- BR-SHP-003: Shipment Settlement
- BR-FIFO-003: Remaining Update
