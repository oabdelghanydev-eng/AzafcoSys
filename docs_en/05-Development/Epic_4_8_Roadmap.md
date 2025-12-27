# 📋 Epic 4-8: Development Roadmap & Edge Cases

**Date:** 2025-12-16  
**Version:** 3.0  
**Status:** Epic 4 ✅ Completed | Epic 5-8 In Development

---

## 📊 Executive Summary

| Epic | Domain | Priority | Complexity | Status |
|------|--------|---------|---------|---------------|
| Epic 4 | Inventory & FIFO | 🟢 Done | High | ✅ Completed |
| Epic 5 | Sales & Invoicing | 🔴 Critical | High | 2 Weeks |
| Epic 6 | Collections | 🔴 Critical | Medium | 1 Week |
| Epic 7 | Treasury & Reports | 🟡 High | Medium | 1 Week |
| Epic 8 | AI & Alerts | 🟢 Medium | Low | 1 Week |

---

# 🔷 Epic 4: Shipment & FIFO Inventory System

## 4.1 Functional Requirements

### Shipments CRUD
```
POST   /api/shipments              → Create new shipment
GET    /api/shipments              → List shipments
GET    /api/shipments/{id}         → Shipment details
PUT    /api/shipments/{id}         → Edit shipment (open only) ✅ NEW
DELETE /api/shipments/{id}         → Delete shipment (conditions apply)
POST   /api/shipments/{id}/close   → Close shipment
POST   /api/shipments/{id}/settle  → Settle shipment
POST   /api/shipments/{id}/unsettle→ Unsettle shipment
```

### Shipment States (State Machine)
```
┌──────────┐     close()      ┌──────────┐    settle()    ┌──────────┐
│   OPEN   │ ───────────────► │  CLOSED  │ ──────────────►│ SETTLED  │
└──────────┘                  └──────────┘                └──────────┘
     │                              │                           │
     │ sell items                   │ unsettle()               │ unsettle()
     ▼                              ▼                           ▼
 remaining_qty--              Cannot sell              Restore remaining
```

## 4.2 Edge Cases & Business Rules

### EC-SHP-001: Selling more than available
```php
// Scenario: Customer requests 100kg, only 80kg available
// Expected: BusinessException('Insufficient stock')
// Action: Show available quantity to user

if ($requestedQty > $availableStock) {
    throw new BusinessException(
        'STK_001',
        "Insufficient stock. Requested: {$requestedQty}, Available: {$availableStock}"
    );
}
```

### EC-SHP-002: Deleting shipment with sales
```php
// Scenario: Attempt to delete a shipment that has sales
// Expected: Prevent deletion
// Rule: sold_quantity > 0 → Cannot Delete

if ($shipment->items()->where('sold_quantity', '>', 0)->exists()) {
    throw new BusinessException('SHP_002', 'Cannot delete shipment with sales');
}
```

### EC-SHP-003: Settling shipment with remaining stock
```php
// Scenario: Settling shipment with remaining_quantity > 0
// Expected: Require next shipment selection or write-off confirmation
// Options:
//   1. carryover → move to next shipment
//   2. write_off → loss (wastage)

if ($shipment->items()->where('remaining_quantity', '>', 0)->exists()) {
    // Must provide next_shipment_id or confirm write_off
}
```

### EC-SHP-004: Modifying shipment price after sales
```php
// Scenario: Changing unit_cost after sales occurred
// Expected: Prevent modification as it affects profit calculations
// Alternative: Create separate Correction

if ($shipmentItem->sold_quantity > 0) {
    throw new BusinessException('SHP_005', 'Cannot modify price of sold items');
}
```

### EC-SHP-005: Concurrent Sales (Race Condition)
```php
// Scenario: Two users selling from same shipment simultaneously
// Problem: Both see remaining_quantity = 50, both sell 40
// Solution: Pessimistic Locking

DB::transaction(function () use ($invoiceData) {
    $items = ShipmentItem::where('remaining_quantity', '>', 0)
        ->lockForUpdate() // ← Critical!
        ->get();
    
    // Now safely allocate
});
```

### EC-SHP-006: FIFO across multiple shipments
```php
// Scenario: Sell 150kg, Shipment 1 has 100, Shipment 2 has 100
// Expected: Deduct 100 from Shipment 1, then 50 from Shipment 2
// FIFO Order: By fifo_sequence then shipment_items.id

$allocations = collect();
$remaining = $requestedQty;

$availableItems = ShipmentItem::query()
    ->where('remaining_quantity', '>', 0)
    ->join('shipments', ...)
    ->orderBy('shipments.fifo_sequence', 'asc')
    ->orderBy('shipment_items.id', 'asc')
    ->lockForUpdate()
    ->get();

foreach ($availableItems as $item) {
    $take = min($remaining, $item->remaining_quantity);
    $allocations->push([...]);
    $remaining -= $take;
    if ($remaining <= 0) break;
}
```

## 4.3 Tests Required

```php
// Feature Tests
- test_can_create_shipment_with_items()
- test_cannot_delete_shipment_with_sales()
- test_cannot_modify_settled_shipment()
- test_fifo_allocates_from_oldest_first()
- test_fifo_handles_multiple_shipments()
- test_fifo_throws_on_insufficient_stock()
- test_carryover_moves_remaining_to_next()
- test_unsettle_restores_carryover()

// Unit Tests
- test_fifo_sequence_ordering()
- test_remaining_quantity_calculation()
- test_sold_quantity_updates_on_sale()
```

---

# 🔷 Epic 5: Sales & Invoicing System

## 5.1 Functional Requirements

### Invoice Structure
```sql
invoices (
    id, invoice_number, customer_id, date,
    type ENUM('sale', 'wastage', 'return'),
    status ENUM('active', 'cancelled'),
    subtotal, discount, total, balance,
    created_by, cancelled_by, cancelled_at
)

invoice_items (
    id, invoice_id, product_id, shipment_item_id,
    cartons, quantity, unit_price, subtotal
)
```

## 5.2 Edge Cases & Business Rules

### EC-INV-001: Invoice with discount greater than subtotal
```php
// Scenario: subtotal = 1000, discount = 1500
// Expected: Validation Error
// Rule: discount <= subtotal

'discount' => 'numeric|min:0|lte:subtotal'
```

### EC-INV-002: Editing invoice outside edit window
```php
// Scenario: Editing invoice from 3 days ago
// Rule: 2 days only (Today + Yesterday)
// Config: settings.invoice_edit_window_days = 2

$editWindow = Setting::get('invoice_edit_window_days', 2);
$cutoffDate = now()->subDays($editWindow)->startOfDay();

if ($invoice->date < $cutoffDate) {
    throw new BusinessException('INV_002', 'Invoice is outside edit window');
}
```

### EC-INV-003: Cancelling partially paid invoice
```php
// Scenario: Invoice 1000, paid 400
// Expected: Prevent cancellation or warn
// Decision: Prevent cancellation if paid > 0

if ($invoice->paid_amount > 0) {
    throw new BusinessException(
        'INV_003',
        'Cannot cancel paid invoice. Paid: ' . $invoice->paid_amount
    );
}
```

### EC-INV-004: Reducing quantity after sale
```php
// Scenario: Invoice 100kg, user wants to reduce to 60kg
// Problem: Must return 40kg to FIFO
// Solution: Reverse allocation for cancelled amount

$diff = $oldQty - $newQty;
if ($diff > 0) {
    $this->fifoService->reversePartialAllocation($invoiceItem, $diff);
}
```

### EC-INV-005: Wastage Invoice
```php
// Scenario: Lost goods (damaged/missing)
// Expected: Deduct from FIFO without adding to customer balance
// Type: type = 'wastage'

if ($invoice->type === 'wastage') {
    // FIFO allocation happens
    // But customer balance is NOT affected
    $invoice->update(['balance' => 0]); // No receivable
}
```

### EC-INV-006: Prevent Invoice Deletion
```php
// Scenario: Any deletion attempt
// Rule: Invoices are never deleted, only cancelled
// Implementation: Observer + Policy

// InvoiceObserver
public function deleting(Invoice $invoice): void
{
    throw new BusinessException('INV_001', 'Invoices cannot be deleted. Use cancellation.');
}
```

### EC-INV-007: Concurrent Invoice Creation
```php
// Scenario: Two employees creating invoice for same customer simultaneously
// Problem: duplicate invoice numbers or race condition on balance
// Solution: 
//   1. Unique invoice_number (DB constraint)
//   2. lockForUpdate() on Customer

DB::transaction(function () {
    $customer = Customer::lockForUpdate()->find($customerId);
    // Create invoice
    // Update balance
});
```

## 5.3 Tests Required

```php
// Feature Tests
- test_can_create_invoice_with_items()
- test_invoice_uses_fifo_allocation()
- test_invoice_updates_customer_balance()
- test_cannot_delete_invoice()
- test_can_cancel_invoice()
- test_cancel_restores_fifo_quantities()
- test_cancel_reduces_customer_balance()
- test_cannot_cancel_paid_invoice()
- test_edit_window_restriction()
- test_wastage_invoice_no_balance()

// Unit Tests
- test_invoice_number_generation()
- test_discount_validation()
- test_balance_calculation()
```

---

# 🔷 Epic 6: Collections System

## 6.1 Functional Requirements

### Collection Structure
```sql
collections (
    id, receipt_number, customer_id, date,
    amount, payment_method ENUM('cash', 'bank'),
    distribution_method ENUM('auto', 'manual'),
    notes, created_by
)

collection_allocations (
    id, collection_id, invoice_id,
    amount, allocated_at
)
```

## 6.2 Edge Cases & Business Rules

### EC-COL-001: Collection greater than customer balance
```php
// Scenario: Balance 500, Collection 800
// Options:
//   1. Reject → Do not allow
//   2. Credit → Allow (Customer becomes creditor)
// Current Rule: Allow (customer becomes creditor)

// No validation needed, balance can go negative
```

### EC-COL-002: Distribution to specific invoices (Manual)
```php
// Scenario: Customer pays 1000, has invoices: 400, 600, 300
// Wants to pay invoices 400 and 600 only
// Validation: Sum of distributions = Payment Amount

$totalAllocations = collect($allocations)->sum('amount');
if ($totalAllocations !== $collection->amount) {
    throw new BusinessException(
        'COL_002',
        'Sum of allocations must equal collection amount'
    );
}
```

### EC-COL-003: Allocation to cancelled invoice
```php
// Scenario: Attempt to allocate to invoice with status = cancelled
// Expected: Validation Error

if ($invoice->status === 'cancelled') {
    throw new BusinessException('COL_003', 'Cannot collect on cancelled invoice');
}
```

### EC-COL-004: Cancelling Collection
```php
// Scenario: Cancel recorded collection
// Actions:
//   1. Restore amount to invoice balances
//   2. Reduce customer.balance
//   3. Reverse transaction in Cashbox/Bank

DB::transaction(function () use ($collection) {
    // Restore invoice balances
    foreach ($collection->allocations as $allocation) {
        $allocation->invoice->increment('balance', $allocation->amount);
        $allocation->invoice->decrement('paid_amount', $allocation->amount);
    }
    
    // Restore customer balance
    $collection->customer->increment('balance', $collection->amount);
    
    // Reverse cashbox/bank transaction
    $this->reverseTransaction($collection);
    
    // Delete allocations
    $collection->allocations()->delete();
    $collection->delete();
});
```

### EC-COL-005: Race Condition on same Invoice
```php
// Scenario: Two employees collecting on same invoice
// Problem: over-allocation (paying more than required)
// Solution: lockForUpdate

$invoice = Invoice::where('balance', '>', 0)
    ->lockForUpdate()
    ->find($invoiceId);

$maxAllocatable = $invoice->balance;
$actualAmount = min($requestedAmount, $maxAllocatable);
```

### EC-COL-006: FIFO vs LIFO Distribution
```php
// FIFO: Oldest First (default)
Invoice::where('customer_id', $customerId)
    ->where('balance', '>', 0)
    ->orderBy('date', 'asc')
    ->orderBy('id', 'asc');

// LIFO: Newest First (optional)
Invoice::where('customer_id', $customerId)
    ->where('balance', '>', 0)
    ->orderBy('date', 'desc')
    ->orderBy('id', 'desc');
```

## 6.3 Tests Required

```php
// Feature Tests
- test_can_create_collection()
- test_auto_fifo_distribution()
- test_auto_lifo_distribution()
- test_manual_distribution()
- test_collection_updates_invoice_balance()
- test_collection_updates_customer_balance()
- test_cannot_over_allocate_invoice()
- test_cancel_collection_restores_balances()
- test_collection_creates_cashbox_transaction()
```

---

# 🔷 Epic 7: Treasury, Bank & Reports

## 7.1 Treasury Management

### Account Structure
```sql
accounts (
    id, type ENUM('cashbox', 'bank'),
    name, balance, is_active
)

account_transactions (
    id, account_id, type,
    amount, running_balance,
    reference_type, reference_id,
    description, created_by, created_at
)
```

### Transaction Types
| Type | Direction | Source |
|------|-----------|--------|
| collection | + | Collection from customer |
| expense | - | Expense (Company/Supplier) |
| deposit | + | Deposit (Cashbox) / Transfer In |
| withdrawal | - | Withdrawal (Cashbox) / Transfer Out |
| transfer_in | + | Transfer In |
| transfer_out | - | Transfer Out |

## 7.2 Edge Cases

### EC-TRS-001: Negative Cashbox Balance
```php
// Scenario: Withdraw 5000, balance 3000
// Rule: Prevent withdrawal exceeding balance
// Exception: Bank to Cashbox transfer (pending)

if ($withdrawal > $account->balance) {
    throw new BusinessException('TRS_001', 'Insufficient cashbox balance');
}
```

### EC-TRS-002: Transfer between Cashbox and Bank
```php
// Scenario: Transfer 10000 from Cashbox to Bank
// Atomic: Both operations must succeed or fail together

DB::transaction(function () use ($amount) {
    $cashbox = Account::where('type', 'cashbox')->lockForUpdate()->first();
    $bank = Account::where('type', 'bank')->lockForUpdate()->first();
    
    if ($cashbox->balance < $amount) {
        throw new BusinessException('TRS_001', 'Insufficient cashbox balance');
    }
    
    $cashbox->decrement('balance', $amount);
    $bank->increment('balance', $amount);
    
    // Create linked transactions
});
```

### EC-TRS-003: Running Balance Calculation
```php
// Each transaction saves the balance after it
$previousBalance = $account->getLastTransaction()?->running_balance ?? 0;
$newBalance = $transaction->type === 'deposit' 
    ? $previousBalance + $amount 
    : $previousBalance - $amount;

$transaction->running_balance = $newBalance;
$account->balance = $newBalance;
```

## 7.3 Reports

### Daily Closing Report
```php
// Components:
// 1. Opening Balance (Previous Day)
// 2. Summary:
//    - Total Sales
//    - Total Collections
//    - Total Expenses
// 3. Transactions List
// 4. Closing Balance
// 5. Signature Fields

DailyReport::create([
    'date' => today(),
    'opening_cashbox' => $previousDay->closing_cashbox,
    'opening_bank' => $previousDay->closing_bank,
    'total_sales' => Invoice::whereDate('date', today())->sum('total'),
    'total_collections' => Collection::whereDate('date', today())->sum('amount'),
    'total_expenses' => Expense::whereDate('date', today())->sum('amount'),
    'closing_cashbox' => Account::cashbox()->balance,
    'closing_bank' => Account::bank()->balance,
]);
```

### Shipment Settlement Report
```php
// Components:
// 1. Shipment Info (Number, Date, Supplier)
// 2. Items (Products with quantities and prices)
// 3. Summary:
//    - Total Cost
//    - Total Sales
//    - Profit/Loss
//    - Profit Margin
// 4. Carryovers (if any)

$report = [
    'shipment' => $shipment,
    'items' => $shipment->items->map(fn($item) => [
        'product' => $item->product->name,
        'initial_qty' => $item->initial_quantity,
        'sold_qty' => $item->sold_quantity,
        'carryover_out' => $item->carryover_out,
        'cost' => $item->total_cost,
        'sales' => $item->invoiceItems->sum('subtotal'),
        'profit' => $sales - $cost,
    ]),
    'summary' => [
        'total_cost' => $shipment->total_cost,
        'total_sales' => $totalSales,
        'profit' => $totalSales - $shipment->total_cost,
        'margin' => ($profit / $totalCost) * 100,
    ],
];
```

## 7.4 Tests Required

```php
// Feature Tests
- test_cashbox_deposit()
- test_cashbox_withdrawal()
- test_bank_deposit()
- test_transfer_between_accounts()
- test_cannot_overdraw_cashbox()
- test_running_balance_calculation()
- test_daily_report_generation()
- test_settlement_report_accuracy()
```

---

# 🔷 Epic 8: AI & Alerts

## 8.1 Smart Rules (Zero Cost)

### Automatic Detection
```php
// 1. Price Anomaly
$avgPrice = InvoiceItem::where('product_id', $productId)
    ->whereDate('created_at', '>=', now()->subDays(30))
    ->avg('unit_price');

if (abs($currentPrice - $avgPrice) / $avgPrice > 0.3) {
    Alert::create([
        'type' => 'price_anomaly',
        'message' => "Price {$currentPrice} deviates from average {$avgPrice} by more than 30%",
    ]);
}

// 2. Late Shipment
$openDays = $shipment->date->diffInDays(now());
$expectedDays = Setting::get('expected_shipment_duration', 14);

if ($openDays > $expectedDays) {
    Alert::create([
        'type' => 'shipment_delay',
        'message' => "Shipment {$shipment->number} open for {$openDays} days",
    ]);
}

// 3. Overdue Customer
$overdueDays = Setting::get('overdue_threshold_days', 30);
$overdueCustomers = Customer::where('balance', '>', 0)
    ->whereHas('invoices', fn($q) => 
        $q->where('balance', '>', 0)
          ->where('date', '<', now()->subDays($overdueDays))
    )
    ->get();
```

## 8.2 Gemini Integration (Optional)

```php
// Dashboard Insights
$prompt = "
Based on the following data:
- Today's Sales: {$todaySales}
- Yesterday's Sales: {$yesterdaySales}
- Average Sales: {$avgSales}
- Open Shipments: {$openShipments}

Provide 3 concise insights in English.
";

$insights = Gemini::generate($prompt);
```

## 8.3 Tests Required

```php
// Unit Tests
- test_price_anomaly_detection()
- test_shipment_delay_detection()
- test_overdue_customer_detection()
- test_alert_creation()

// Feature Tests
- test_alerts_api_endpoint()
- test_resolve_alert()
```

---

# 📋 Testing Summary Template

## For Each Epic:

### Unit Tests
- [ ] Service logic tests
- [ ] Calculation tests
- [ ] Validation tests

### Feature Tests
- [ ] CRUD operations
- [ ] Permission checks
- [ ] Edge cases
- [ ] Error handling

### Integration Tests
- [ ] Multi-model interactions
- [ ] Transaction integrity
- [ ] Event firing

---

# 🔧 Implementation Checklist

## Before starting each Epic:
- [ ] Review requirements
- [ ] Review Edge Cases
- [ ] Create Tests structure first (TDD)
- [ ] Review migrations

## After completion:
- [ ] All tests pass
- [ ] Documentation updated
- [ ] No Breaking Changes
- [ ] Performance OK

---

*Last Updated: 2025-12-16*
*Version: 2.0*
