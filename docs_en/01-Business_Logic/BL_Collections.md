# Collection Business Logic

## 📋 Overview

Collections process represents the **customer payment** for their invoices.
The system supports both Automatic Distribution (FIFO) and Manual Distribution.

---

## 🔄 Collection Lifecycle

```
┌─────────────┐
│   Create    │
│  Collection │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│ CollectionObserver      │
│ customer.balance -=     │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ CollectionService       │
│ FIFO Allocation         │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ CollectionAllocations   │
│ Distribute to Invoices  │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ AllocationObserver      │
│ invoice.paid_amount +=  │
│ invoice.balance -=      │
└─────────────────────────┘
```

---

## 📊 Decision Table: Distribution

| Case | distribution_method | Behavior |
|--------|---------------------|--------|
| Default FIFO | oldest_first | Oldest first |
| LIFO | newest_first | Newest first |
| Manual | manual | Link to specific invoice |
| Amount > Debts | Any | Excess = Credit Balance |

---

## 🧮 Calculations

### 1. FIFO Distribution

```
Input:
  - customer_id
  - amount (Collected Amount)

Algorithm:
  remaining = amount
  invoices = GET unpaid invoices ORDER BY date ASC
  
  FOR EACH invoice IN invoices:
    IF remaining <= 0: BREAK
    
    allocate = MIN(remaining, invoice.balance)
    
    CREATE CollectionAllocation(invoice, allocate)
    
    remaining -= allocate
  
  IF remaining > 0:
    // Excess = Credit Balance for Customer
    // customer.balance became negative automatically
```

### 2. Collection Impact on Balances

```
When creating a collection:
┌─────────────────────────────────────────────────┐
│ customer.balance -= collection.amount           │
│                                                 │
│ For each allocation:                            │
│   invoice.paid_amount += allocation.amount      │
│   invoice.balance -= allocation.amount          │
└─────────────────────────────────────────────────┘

Possible Balance Results for Customer:
┌──────────────────────────────────┐
│ + Positive = Still in Debt     │
│ 0 Zero = Fully Settled         │
│ - Negative = Has Credit (Excess)│
└──────────────────────────────────┘
```

---

## 📝 Validation Rules

### CreateCollectionRequest

```php
[
    'customer_id' => 'required|exists:customers,id',
    'date' => 'required|date|before_or_equal:today',
    'amount' => 'required|numeric|min:0.01',
    'payment_method' => 'required|in:cash,bank',
    'distribution_method' => 'nullable|in:oldest_first,newest_first,manual',
    'invoice_id' => 'nullable|exists:invoices,id|required_if:distribution_method,manual',
    'notes' => 'nullable|string|max:1000',
]
```

### Custom Validation

```php
// In case of manual distribution: Invoice must belong to the same customer
if ($this->distribution_method === 'manual') {
    $invoice = Invoice::find($this->invoice_id);
    if ($invoice->customer_id !== $this->customer_id) {
        $fail('The invoice does not belong to this customer');
    }
}
```

---

## 🔐 Authorization Rules (Policies)

### CollectionPolicy

```php
class CollectionPolicy
{
    public function view(User $user, Collection $collection): bool
    {
        return $user->hasPermission('view_collections');
    }
    
    public function create(User $user): bool
    {
        return $user->hasPermission('create_collections');
    }
    
    public function update(User $user, Collection $collection): bool
    {
        if (!$user->hasPermission('edit_collections')) {
            return false;
        }
        
        // Edit window
        $editDays = (int) Setting::get('edit_window_days', 1);
        return $collection->date >= now()->subDays($editDays)->startOfDay()
            && $collection->status === 'confirmed';
    }
    
    /**
     * Can cancel collection?
     * ⚠️ Correction 2025-12-13: Cancel instead of Delete
     */
    public function cancel(User $user, Collection $collection): bool
    {
        return $user->hasPermission('cancel_collections')
            && $collection->status === 'confirmed';
    }
    
    // ❌ No delete() - Deletion is completely forbidden
    // Use cancel instead of delete to preserve audit trail
}
```

---

## 🔄 Observer Logic

### CollectionObserver

```php
class CollectionObserver
{
    /**
     * EVENT: created
     */
    public function created(Collection $collection): void
    {
        // Decrease customer balance
        $collection->customer->decrement('balance', $collection->amount);
        
        // Distribution is handled via CollectionService
    }
    
    /**
     * EVENT: updated
     * PURPOSE: Handle Cancellation
     * ⚠️ Correction 2025-12-13: Cancel instead of Delete
     */
    public function updated(Collection $collection): void
    {
        if ($collection->wasChanged('status')) {
            $oldStatus = $collection->getOriginal('status');
            $newStatus = $collection->status;
            
            // Cancel Collection
            if ($oldStatus === 'confirmed' && $newStatus === 'cancelled') {
                // Increase customer balance
                $collection->customer->increment('balance', $collection->amount);
                
                // Delete Allocations
                $collection->allocations()->delete();
                // Observers will return balances to invoices
            }
            
            // Prevent Reactivation
            if ($oldStatus === 'cancelled' && $newStatus === 'confirmed') {
                throw new BusinessException('COL_002', 'Cannot reactivate cancelled collection');
            }
        }
    }
    
    /**
     * EVENT: deleting
     * PURPOSE: ❌ Prevent deletion completely
     * ⚠️ Correction 2025-12-13
     */
    public function deleting(Collection $collection): bool
    {
        throw new BusinessException(
            'COL_001',
            "Cannot delete collections. Use cancellation instead to preserve audit trail."
        );
    }
}
```

### CollectionAllocationObserver

```php
class CollectionAllocationObserver
{
    /**
     * EVENT: created
     * When allocating amount to invoice
     */
    public function created(CollectionAllocation $allocation): void
    {
        $invoice = $allocation->invoice;
        
        // Increase paid amount
        $invoice->increment('paid_amount', $allocation->amount);
        
        // Decrease balance
        $invoice->decrement('balance', $allocation->amount);
    }
    
    /**
     * EVENT: deleted
     * When deleting allocation (reverse operation)
     */
    public function deleted(CollectionAllocation $allocation): void
    {
        $invoice = $allocation->invoice;
        
        $invoice->decrement('paid_amount', $allocation->amount);
        $invoice->increment('balance', $allocation->amount);
    }
}
```

---

## 🛠️ CollectionService - Full Detail

```php
<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class CollectionService
{
    /**
     * Allocate collection amount to invoices
     * 
     * @param Collection $collection The collection to distribute
     * @throws \Exception If operation fails
     */
    public function allocatePayment(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            $remaining = $collection->amount;
            
            // Determine invoice order
            $order = $collection->distribution_method === 'newest_first' 
                ? 'desc' 
                : 'asc';
            
            // Get unpaid invoices with lock for Race Condition protection
            $unpaidInvoices = Invoice::where('customer_id', $collection->customer_id)
                ->where('balance', '>', 0)
                ->where('status', 'active')
                ->orderBy('date', $order)
                ->lockForUpdate()
                ->get();
            
            foreach ($unpaidInvoices as $invoice) {
                if ($remaining <= 0) break;
                
                $allocateAmount = min($remaining, $invoice->balance);
                
                // Create Allocation Record
                // Observer will handle invoice update
                CollectionAllocation::create([
                    'collection_id' => $collection->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                ]);
                
                $remaining -= $allocateAmount;
            }
            
            // If amount remains, it becomes credit for customer
            // (customer.balance becomes negative automatically via CollectionObserver)
        });
    }
    
    /**
     * Manual allocation to specific invoice
     */
    public function allocateToInvoice(Collection $collection, Invoice $invoice): void
    {
        DB::transaction(function () use ($collection, $invoice) {
            $allocateAmount = min($collection->amount, $invoice->balance);
            
            CollectionAllocation::create([
                'collection_id' => $collection->id,
                'invoice_id' => $invoice->id,
                'amount' => $allocateAmount,
            ]);
            
            // Excess remains as credit
        });
    }
    
    /**
     * Reverse Allocations
     */
    public function reverseAllocations(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            // Observers will handle invoice updates
            $collection->allocations()->delete();
        });
    }
}
```

---

## 📊 Flowchart: Create Collection

```
┌─────────────┐
│    Start    │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│ Validation Request      │
└───────────┬─────────────┘
            │
    ┌───────┴───────┐
    │               │
   ✅              ❌
    │               │
    ▼               ▼
┌─────────┐    ┌──────────┐
│ Proceed  │    │ 422 Error│
└────┬────┘    └──────────┘
     │
     ▼
┌─────────────────────────┐
│ DB::transaction START   │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│   Create Collection     │
│   - generate receipt_number│
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│ CollectionObserver      │
│ customer.balance -=     │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────────────────┐
│ distribution_method?                │
├──────────────┬──────────────────────┤
│ oldest_first │ newest_first│manual  │
└──────┬───────┴──────┬──────┴───┬────┘
       │              │          │
       ▼              ▼          ▼
┌──────────────┐┌──────────────┐┌──────────────┐
│   FIFO ASC   ││   FIFO DESC  ││ Single Invoice│
└──────┬───────┘└──────┬───────┘└──────┬───────┘
       │              │                │
       └──────────────┴────────────────┘
                      │
                      ▼
       ┌─────────────────────────┐
       │ CollectionService       │
       │ allocatePayment()       │
       └───────────┬─────────────┘
                   │
                   ▼
       ┌─────────────────────────┐
       │ CREATE Allocations      │
       │ foreach invoice         │
       └───────────┬─────────────┘
                   │
                   ▼
       ┌─────────────────────────┐
       │ AllocationObserver      │
       │ invoice.paid_amount +=  │
       │ invoice.balance -=      │
       └───────────┬─────────────┘
                   │
                   ▼
       ┌─────────────────────────┐
       │ DB::transaction COMMIT  │
       └───────────┬─────────────┘
                   │
                   ▼
       ┌─────────────────────────┐
       │ Return Collection JSON  │
       │ + Allocations           │
       └─────────────────────────┘
```

---

## ⚠️ Edge Cases

### 1. Collection Larger than Total Debts

```
Case:
  - customer.balance = 500 (Debt)
  - collection.amount = 700

Result:
  - Distribute 500 to invoices
  - customer.balance = -200 (Credit)
  - remaining = 200 (No allocation created)
```

### 2. Customer has No Debts

```
Case:
  - customer.balance = 0
  - collection.amount = 100

Result:
  - No invoices to distribute to
  - customer.balance = -100 (Credit)
```

### 3. Delete Collection

```
Case:
  - collection.amount = 500
  - Distributed to 3 invoices

Action:
  1. CASCADE DELETE on allocations
  2. Each allocation triggers deleted event
  3. Each invoice regains its balance
  4. customer.balance += 500
```

### 4. Race Condition - Concurrent Collections

```
Problem:
  User A: Collects 500
  User B: Collects 300
  Same customer, same moment

Protection:
  lockForUpdate() on invoices
  DB::transaction

Result:
  User A completes first
  User B waits then completes
  Distribution is correct
```

---

## 📈 Performance Considerations

### Required Indexes

```sql
-- collections
INDEX idx_customer (customer_id)
INDEX idx_date (date)
INDEX idx_method (payment_method)

-- collection_allocations
INDEX idx_collection (collection_id)
INDEX idx_invoice (invoice_id)
```

### Eager Loading

```php
// ✅ Correct
$collections = Collection::with([
    'customer',
    'allocations.invoice',
    'createdBy'
])->get();
```

---

## 🔗 Related Rules

- BR-COL-001 to BR-COL-005
- BR-INV-002 (Update Invoice Balance)
- BR-CUS-003 (Decrease Customer Balance)
