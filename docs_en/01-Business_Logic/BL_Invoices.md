# Invoice Business Logic

## 📋 Overview

This file documents **all** details related to invoice logic in the system.

---

## 🔄 Invoice Lifecycle

```
┌─────────────┐
│   Frontend  │
│ LocalStorage│  ← Drafts saved here
└──────┬──────┘
       │ Submit
       ▼
┌─────────────┐     ┌─────────────┐
│   active    │────►│  cancelled  │  
│  (Default)  │     │  (Cancel)   │   
21: └──────┬──────┘     └─────────────┘    
       │
       │ Collections
       ▼
┌─────────────┐
│ balance = 0 │  ← Fully Paid Invoice
│  (Paid)     │
└─────────────┘
```

---

## 📊 Decision Table: Create Invoice

| Condition | Result | Action |
|-------|---------|---------|
| customer_id exists | ✅ | Proceed |
| customer_id missing | ❌ | Validation Error |
| items.length > 0 | ✅ | Proceed |
| items.length = 0 | ❌ | "Invoice must contain at least one item" |
| Every item has shipment_item_id | ✅ | Proceed |
| item without shipment_item_id | ❌ | "Must specify FIFO source" |
| remaining_quantity >= quantity | ✅ | Proceed |
| remaining_quantity < quantity | ❌ | "Required quantity not available" |

---

## 🧮 Calculations

### 1. Total Calculation (⚠️ Correction 2025-12-13)

```
item_total = total_weight_kg × price_per_kg
subtotal   = SUM(items.item_total)
discount   = user_input OR 0
total      = subtotal - discount
```

### 1.1 Weight Entry Modes

> **Determined by setting:** `weight_entry_mode`

| Mode | User Enters | System Calculates |
|-------|---------------|-------------|
| `total_weight` | Unit Count + Total Weight in KG | Unit Weight = Total Weight ÷ Count |
| `unit_weight` | Unit Count + Unit Weight in KG | Total Weight = Count × Unit Weight |

**Important Note:**
- Shipment deduction is by Unit Count (cartons), not KG.
- In Shipment Settlement statement, **Weight Deficit** appears:
  ```
  Weight Deficit = Total Incoming Weight - Total Sales Weight
  ```

### 2. Balance Calculation

```
balance = total - paid_amount

Where:
- paid_amount = SUM(collection_allocations.amount)
- Starts at 0 on creation
```

### 3. Customer Balance Update

```
On Creation:
  customer.balance += invoice.total

On Update (total changed):
  diff = new_total - old_total
  customer.balance += diff

On Cancellation (⚠️ Critical Logic):
  1. Unlink Allocations (Deleted)
     → Each allocation.delete() increases customer.balance by amount
  2. customer.balance -= invoice.total
  3. invoice.balance = 0, invoice.paid_amount = 0
  
  Net Result:
  customer.balance = original - total + paid = original - balance ✅
```

---

## 📝 Validation Rules

### CreateInvoiceRequest

```php
[
    'customer_id' => 'required|exists:customers,id',
    'date' => 'required|date|before_or_equal:today',
    'items' => 'required|array|min:1',
    'items.*.product_id' => 'required|exists:products,id',
    'items.*.shipment_item_id' => 'required|exists:shipment_items,id',
    'items.*.quantity' => 'required|numeric|min:0.001',
    'items.*.unit_price' => 'required|numeric|min:0',
    'discount' => 'nullable|numeric|min:0',
    'type' => 'in:sale,wastage',
    'notes' => 'nullable|string|max:1000',
]
```

### UpdateInvoiceRequest

```php
[
    // Same as creation rules +
    'total' => [
        'required',
        'numeric',
        'min:0',
        // Custom Rule: Cannot be less than paid amount
        function ($attribute, $value, $fail) {
            if ($value < $this->invoice->paid_amount) {
                $fail("Cannot reduce total below paid amount");
            }
        },
    ],
]
```

---

## 🔐 Authorization Rules (Policies)

### InvoicePolicy

```php
class InvoicePolicy
{
    /**
     * Can user view invoice?
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('view_invoices');
    }
    
    /**
     * Can user create invoice?
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_invoices');
    }
    
    /**
     * Can user update invoice?
     * - Edit window
     * - Active status
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if (!$user->hasPermission('edit_invoices')) {
            return false;
        }
        
        // Check edit window
        $editDays = (int) Setting::get('edit_window_days', 1);
        $cutoffDate = now()->subDays($editDays)->startOfDay();
        
        if ($invoice->date < $cutoffDate) {
            return false; // Out of edit window
        }
        
        // Check status
        return $invoice->status === 'active';
    }
    
    /**
     * Can user cancel invoice?
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('cancel_invoices') 
            && $invoice->status === 'active';
    }
    
    // ❌ No delete() - Deletion is completely forbidden
    // Use cancel instead of delete to preserve audit trail
}
```

---

## 🔄 Observer Logic

### InvoiceObserver - Full Detail

```php
class InvoiceObserver
{
    /**
     * EVENT: created
     * TRIGGER: After saving new invoice to DB
     */
    public function created(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // 1. Set balance = total
            $invoice->balance = $invoice->total;
            $invoice->saveQuietly();
            
            // 2. Update customer balance
            $invoice->customer->increment('balance', $invoice->total);
            
            // 3. Log to Audit Log
            AuditLog::create([
                'model_type' => 'Invoice',
                'model_id' => $invoice->id,
                'action' => 'created',
                'new_values' => $invoice->toArray(),
                'user_id' => auth()->id(),
            ]);
        });
    }
    
    /**
     * EVENT: updated
     * SCENARIOS:
     * - Change total
     * - Change status (active → cancelled)
     * - Change status (cancelled → active)
     */
    public function updated(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            
            // Scenario 1: Invoice Cancellation ⚠️ (Critical Logic)
            if ($invoice->wasChanged('status')) {
                $oldStatus = $invoice->getOriginal('status');
                $newStatus = $invoice->status;
                
                if ($oldStatus === 'active' && $newStatus === 'cancelled') {
                    // ⚠️ Step 1: Unlink Allocations first
                    // This will return paid amounts as credit balance to customer
                    $allocations = $invoice->allocations;
                    foreach ($allocations as $allocation) {
                        $allocation->delete(); // Observer increases customer.balance
                    }
                    
                    // ⚠️ Step 2: Decrease Customer Balance by Total
                    $invoice->customer->decrement('balance', $invoice->total);
                    
                    // ⚠️ Step 3: Zero Out Invoice
                    $invoice->balance = 0;
                    $invoice->paid_amount = 0;
                    $invoice->saveQuietly();
                    
                    return;
                }
                
                // Scenario 2: Reactivate Cancelled Invoice
                // ❌ Forbidden now because allocations are deleted
                if ($oldStatus === 'cancelled' && $newStatus === 'active') {
                    throw new \Exception('Cannot reactivate cancelled invoice');
                }
            }
            
            // Scenario 3: Change Invoice Value
            if ($invoice->wasChanged('total')) {
                $oldTotal = $invoice->getOriginal('total');
                $newTotal = $invoice->total;
                
                // Safety Check
                if ($newTotal < $invoice->paid_amount) {
                    throw new \Exception("Cannot reduce total below paid amount");
                }
                
                $diff = $newTotal - $oldTotal;
                $invoice->balance = $newTotal - $invoice->paid_amount;
                $invoice->saveQuietly();
                
                if ($diff > 0) {
                    $invoice->customer->increment('balance', $diff);
                } else {
                    $invoice->customer->decrement('balance', abs($diff));
                }
            }
        });
    }
    
    /**
     * EVENT: deleting
     * PURPOSE: ❌ Prevent Deletion Entirely - Use Cancellation Instead
     * Correction: 2025-12-13
     */
    public function deleting(Invoice $invoice): bool
    {
        throw new \Exception(
            "Cannot delete invoices. Use cancellation instead to preserve audit trail."
        );
    }
}
```

---

## 📊 Flowchart: Create Invoice

```
┌─────────────┐
│    Start    │
└──────┬──────┘
       │
       ▼
┌───────────────┐
│ Check Create  │
│ Permission    │
└──────┬────────┘
       │
   ┌───┴───┐
   │       │
  ✅      ❌
   │       │
   ▼       ▼
┌──────┐  ┌───────┐
│Proceed│  │403 Err│
└──┬───┘  └───────┘
   │
   ▼
┌─────────────────┐
│ Validation Req  │
└──────┬──────────┘
       │
   ┌───┴───┐
   │       │
  ✅      ❌
   │       │
   ▼       ▼
┌──────┐  ┌───────┐
│Proceed│  │422 Err│
└──┬───┘  └───────┘
   │
   ▼
┌─────────────────┐
│ FIFO Alloc Check│
│ Availability    │
└──────┬──────────┘
       │
   ┌───┴───┐
   │       │
  ✅      ❌
   │       │
   ▼       ▼
┌──────┐  ┌──────────────────┐
│Proceed│  │"Not Available"   │
└──┬───┘  └──────────────────┘
   │
   ▼
┌─────────────────┐
│ DB::trans START │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ Create Invoice  │
│ - invoice_num   │
│ - calc total    │
│ - balance=total │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ Create Items    │
│ - link shipment │
│ - deduct qty    │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ Observer::create│
│ - cust.balance+ │
│ - AuditLog      │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ DB::trans COMMIT│
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│ Return JSON     │
│ 201 Created     │
└─────────────────┘
```

---

## ⚠️ Edge Cases

### 1. Invoice with Discount Larger than Total

```
Problem: discount > subtotal
Result: total negative

Solution: Validation
'discount' => 'lte:subtotal'
```

### 2. Update Partially Paid Invoice

```
Case: total = 1000, paid = 500

Attempt: Update total to 400
Problem: 400 < 500

Solution: 
if ($newTotal < $invoice->paid_amount) {
    throw Exception;
}
```

### 3. Cancel Partially Paid Invoice ⚠️ Correction 2025-12-16

> **Design Decision (EC-INV-003):** Forbidden to cancel paid invoice.

```
Case: total = 1000, paid = 500, balance = 500

Attempt: status → cancelled
Result: ❌ INV_008 "Cannot cancel paid invoice"

Reason:
- Avoid complexity of returning payments
- Maintain data integrity
- If cancellation is needed, must cancel collections first
```

### 4. Cancellation Only (No Deletion) ⚠️ Correction 2025-12-13

> **Design Decision:** Deletion is strictly forbidden. Use cancellation only.

```
Cancellation:
- Keeps record for history (Audit Trail)
- ❌ Forbidden if paid_amount > 0 (EC-INV-003)
- Returns quantities to inventory

Deletion:
❌ Strictly Forbidden
❌ Throws BusinessException in Observer
```

---

## 📈 Performance Considerations

### Required Indexes

```sql
INDEX idx_customer (customer_id)     -- Search by customer
INDEX idx_date (date)                -- Daily reports
INDEX idx_balance (balance)          -- Unpaid invoices
INDEX idx_status (status)            -- Filtering
INDEX idx_number (invoice_number)    -- Search by number
```

### N+1 Prevention

```php
// ❌ Wrong
$invoices = Invoice::all();
foreach ($invoices as $invoice) {
    echo $invoice->customer->name; // N+1
}

// ✅ Correct
$invoices = Invoice::with('customer', 'items.product')->get();
```

---

## 🔗 Related Rules

- BR-INV-001 to BR-INV-007
- BR-COL-004 (Update invoice on collection)
- BR-FIFO-002 (Track inventory source)

---

## 📜 Change Log

| Date | Modification | Reason |
|---------|---------|-------|
| 2025-12-13 | Corrected Total Calc: `total_weight_kg × price_per_kg` | Was wrong: `quantity × unit_price` |
| 2025-12-13 | Added Weight Entry Modes (total_weight / unit_weight) | Clarify entry logic |
| 2025-12-13 | Removed Deletion Logic - Cancellation Only | Design Decision: Preserve Audit Trail |
| 2025-12-13 | Added Weight Deficit in Settlement Statement | Clarify shipment report |
| 2025-12-16 | EC-INV-003: Prevent cancelling paid invoices (INV_008) | Design Decision: Avoid payment return complexity |
