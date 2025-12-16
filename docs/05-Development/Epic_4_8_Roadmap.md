# 📋 Epic 4-8: Development Roadmap & Edge Cases

**التاريخ:** 2025-12-16
**الإصدار:** 2.0
**الحالة:** للتطوير المستقبلي

---

## 📊 الملخص التنفيذي

| Epic | المجال | الأولوية | التعقيد | الوقت المُقدر |
|------|--------|---------|---------|---------------|
| Epic 4 | Inventory & FIFO | 🔴 Critical | High | 2 أسابيع |
| Epic 5 | Sales & Invoicing | 🔴 Critical | High | 2 أسابيع |
| Epic 6 | Collections | 🔴 Critical | Medium | 1 أسبوع |
| Epic 7 | Treasury & Reports | 🟡 High | Medium | 1 أسبوع |
| Epic 8 | AI & Alerts | 🟢 Medium | Low | 1 أسبوع |

---

# 🔷 Epic 4: نظام الشحنات والمخزون FIFO

## 4.1 المتطلبات الوظيفية

### Shipments CRUD
```
POST   /api/shipments              → إنشاء شحنة جديدة
GET    /api/shipments              → قائمة الشحنات
GET    /api/shipments/{id}         → تفاصيل شحنة
PUT    /api/shipments/{id}         → تعديل شحنة (open only)
DELETE /api/shipments/{id}         → حذف شحنة (conditions apply)
POST   /api/shipments/{id}/close   → إغلاق شحنة
POST   /api/shipments/{id}/settle  → تصفية شحنة
POST   /api/shipments/{id}/unsettle→ إلغاء تصفية
```

### حالات الشحنة (State Machine)
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

### EC-SHP-001: بيع كمية أكبر من المتاح
```php
// Scenario: عميل يطلب 100 كجم، المتاح 80 كجم فقط
// Expected: BusinessException('المخزون غير كافي')
// Action: عرض الكمية المتاحة للمستخدم

if ($requestedQty > $availableStock) {
    throw new BusinessException(
        'STK_001',
        "المخزون غير كافي. المطلوب: {$requestedQty}، المتاح: {$availableStock}"
    );
}
```

### EC-SHP-002: حذف شحنة لها مبيعات
```php
// Scenario: محاولة حذف شحنة تم البيع منها
// Expected: منع الحذف
// Rule: sold_quantity > 0 → Cannot Delete

if ($shipment->items()->where('sold_quantity', '>', 0)->exists()) {
    throw new BusinessException('SHP_002', 'لا يمكن حذف شحنة لها مبيعات');
}
```

### EC-SHP-003: تصفية شحنة بها بضاعة متبقية
```php
// Scenario: تصفية شحنة ولديها remaining_quantity > 0
// Expected: طلب تحديد شحنة الترحيل أو السماح بالخسارة
// Options:
//   1. carryover → نقل للشحنة التالية
//   2. write_off → خسارة (wastage)

if ($shipment->items()->where('remaining_quantity', '>', 0)->exists()) {
    // Must provide next_shipment_id or confirm write_off
}
```

### EC-SHP-004: تعديل سعر شحنة بعد البيع منها
```php
// Scenario: تغيير unit_cost بعد إتمام مبيعات
// Expected: منع التعديل لأنه يؤثر على حسابات الربح
// Alternative: إنشاء تعديل (Correction) منفصل

if ($shipmentItem->sold_quantity > 0) {
    throw new BusinessException('SHP_005', 'لا يمكن تعديل سعر بضاعة مباعة');
}
```

### EC-SHP-005: Concurrent Sales (Race Condition)
```php
// Scenario: مستخدمان يبيعان من نفس الشحنة في نفس اللحظة
// Problem: كلاهما يرى remaining_quantity = 50، كلاهما يبيع 40
// Solution: Pessimistic Locking

DB::transaction(function () use ($invoiceData) {
    $items = ShipmentItem::where('remaining_quantity', '>', 0)
        ->lockForUpdate() // ← Critical!
        ->get();
    
    // Now safely allocate
});
```

### EC-SHP-006: FIFO عبر شحنات متعددة
```php
// Scenario: بيع 150 كجم، الشحنة 1 = 100، الشحنة 2 = 100
// Expected: خصم 100 من الشحنة 1، ثم 50 من الشحنة 2
// FIFO Order: حسب fifo_sequence ثم shipment_items.id

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

# 🔷 Epic 5: نظام المبيعات والفوترة

## 5.1 المتطلبات الوظيفية

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

### EC-INV-001: فاتورة بخصم أكبر من المجموع
```php
// Scenario: subtotal = 1000, discount = 1500
// Expected: Validation Error
// Rule: discount <= subtotal

'discount' => 'numeric|min:0|lte:subtotal'
```

### EC-INV-002: تعديل فاتورة خارج نافذة التعديل
```php
// Scenario: تعديل فاتورة منذ 3 أيام
// Rule: يومين فقط (اليوم + أمس)
// Config: settings.invoice_edit_window_days = 2

$editWindow = Setting::get('invoice_edit_window_days', 2);
$cutoffDate = now()->subDays($editWindow)->startOfDay();

if ($invoice->date < $cutoffDate) {
    throw new BusinessException('INV_002', 'الفاتورة خارج نافذة التعديل');
}
```

### EC-INV-003: إلغاء فاتورة مدفوعة جزئياً
```php
// Scenario: فاتورة 1000، مدفوع منها 400
// Expected: منع الإلغاء أو السماح مع التحذير
// Decision: منع الإلغاء إذا paid > 0

if ($invoice->paid_amount > 0) {
    throw new BusinessException(
        'INV_003',
        'لا يمكن إلغاء فاتورة مدفوعة. المدفوع: ' . $invoice->paid_amount
    );
}
```

### EC-INV-004: تقليل كمية الفاتورة بعد البيع
```php
// Scenario: فاتورة 100 كجم، المستخدم يريد تعديلها لـ 60 كجم
// Problem: يجب إرجاع 40 كجم للـ FIFO
// Solution: إعادة allocation للكمية المُلغاة

$diff = $oldQty - $newQty;
if ($diff > 0) {
    $this->fifoService->reversePartialAllocation($invoiceItem, $diff);
}
```

### EC-INV-005: فاتورة هالك (Wastage)
```php
// Scenario: فقدان بضاعة (تالفة/مفقودة)
// Expected: خصم من FIFO بدون إضافة لرصيد العميل
// Type: type = 'wastage'

if ($invoice->type === 'wastage') {
    // FIFO allocation happens
    // But customer balance is NOT affected
    $invoice->update(['balance' => 0]); // No receivable
}
```

### EC-INV-006: منع حذف الفاتورة
```php
// Scenario: أي محاولة حذف
// Rule: الفواتير لا تُحذف أبداً، فقط تُلغى
// Implementation: Observer + Policy

// InvoiceObserver
public function deleting(Invoice $invoice): void
{
    throw new BusinessException('INV_001', 'الفواتير لا تُحذف. استخدم الإلغاء.');
}
```

### EC-INV-007: Concurrent Invoice Creation
```php
// Scenario: موظفان يُنشئان فاتورة في نفس اللحظة لنفس العميل
// Problem: duplicate invoice numbers أو race على الـ balance
// Solution: 
//   1. Unique invoice_number (DB constraint)
//   2. lockForUpdate() على Customer

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

# 🔷 Epic 6: نظام التحصيلات

## 6.1 المتطلبات الوظيفية

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

### EC-COL-001: تحصيل أكبر من رصيد العميل
```php
// Scenario: رصيد العميل 500، التحصيل 800
// Options:
//   1. Reject → لا تسمح
//   2. Credit → اسمح (العميل يصبح دائن)
// Current Rule: Allow (customer becomes creditor)

// No validation needed, balance can go negative
```

### EC-COL-002: توزيع على فواتير محددة (Manual)
```php
// Scenario: عميل يدفع 1000، لديه فواتير: 400, 600, 300
// يريد الدفع للفاتورتين 400 و 600 فقط
// Validation: مجموع التوزيعات = المبلغ المدفوع

$totalAllocations = collect($allocations)->sum('amount');
if ($totalAllocations !== $collection->amount) {
    throw new BusinessException(
        'COL_002',
        'مجموع التوزيعات يجب أن يساوي المبلغ المدفوع'
    );
}
```

### EC-COL-003: توزيع على فاتورة مُلغاة
```php
// Scenario: محاولة تخصيص لفاتورة status = cancelled
// Expected: Validation Error

if ($invoice->status === 'cancelled') {
    throw new BusinessException('COL_003', 'لا يمكن التحصيل على فاتورة ملغاة');
}
```

### EC-COL-004: إلغاء تحصيل
```php
// Scenario: إلغاء تحصيل مُسجل
// Actions:
//   1. إرجاع المبلغ لأرصدة الفواتير
//   2. تقليل customer.balance
//   3. عكس transaction في Cashbox/Bank

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

### EC-COL-005: Race Condition على نفس الفاتورة
```php
// Scenario: موظفان يُحصّلان على نفس الفاتورة
// Problem: over-allocation (دفع أكثر من المطلوب)
// Solution: lockForUpdate

$invoice = Invoice::where('balance', '>', 0)
    ->lockForUpdate()
    ->find($invoiceId);

$maxAllocatable = $invoice->balance;
$actualAmount = min($requestedAmount, $maxAllocatable);
```

### EC-COL-006: FIFO vs LIFO Distribution
```php
// FIFO: الأقدم أولاً (default)
Invoice::where('customer_id', $customerId)
    ->where('balance', '>', 0)
    ->orderBy('date', 'asc')
    ->orderBy('id', 'asc');

// LIFO: الأحدث أولاً (optional)
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

# 🔷 Epic 7: الخزنة والبنك والتقارير

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
| collection | + | تحصيل من عميل |
| expense | - | مصروف (شركة/مورد) |
| deposit | + | إيداع (خزنة) / تحويل وارد |
| withdrawal | - | سحب (خزنة) / تحويل صادر |
| transfer_in | + | تحويل وارد |
| transfer_out | - | تحويل صادر |

## 7.2 Edge Cases

### EC-TRS-001: رصيد سالب في الخزنة
```php
// Scenario: سحب 5000 والرصيد 3000
// Rule: منع السحب أكثر من المتاح
// Exception: تحويل من البنك للخزنة (معلق)

if ($withdrawal > $account->balance) {
    throw new BusinessException('TRS_001', 'رصيد الخزنة غير كافي');
}
```

### EC-TRS-002: تحويل بين الخزنة والبنك
```php
// Scenario: تحويل 10000 من الخزنة للبنك
// Atomic: يجب أن تتم العمليتين معاً أو لا تتم

DB::transaction(function () use ($amount) {
    $cashbox = Account::where('type', 'cashbox')->lockForUpdate()->first();
    $bank = Account::where('type', 'bank')->lockForUpdate()->first();
    
    if ($cashbox->balance < $amount) {
        throw new BusinessException('TRS_001', 'رصيد الخزنة غير كافي');
    }
    
    $cashbox->decrement('balance', $amount);
    $bank->increment('balance', $amount);
    
    // Create linked transactions
});
```

### EC-TRS-003: Running Balance Calculation
```php
// كل transaction يحفظ الرصيد بعده
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
// المكونات:
// 1. Opening Balance (من اليوم السابق)
// 2. Summary:
//    - إجمالي المبيعات
//    - إجمالي التحصيلات
//    - إجمالي المصروفات
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
// المكونات:
// 1. معلومات الشحنة (رقم، تاريخ، مورد)
// 2. البنود (المنتجات مع الكميات والأسعار)
// 3. الملخص:
//    - إجمالي التكلفة
//    - إجمالي المبيعات
//    - الربح/الخسارة
//    - نسبة الربح
// 4. الترحيلات (إن وجدت)

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

# 🔷 Epic 8: الذكاء الاصطناعي والتنبيهات

## 8.1 Smart Rules (Zero Cost)

### الكشف التلقائي
```php
// 1. سعر شاذ
$avgPrice = InvoiceItem::where('product_id', $productId)
    ->whereDate('created_at', '>=', now()->subDays(30))
    ->avg('unit_price');

if (abs($currentPrice - $avgPrice) / $avgPrice > 0.3) {
    Alert::create([
        'type' => 'price_anomaly',
        'message' => "السعر {$currentPrice} يختلف عن المتوسط {$avgPrice} بنسبة أكبر من 30%",
    ]);
}

// 2. شحنة متأخرة
$openDays = $shipment->date->diffInDays(now());
$expectedDays = Setting::get('expected_shipment_duration', 14);

if ($openDays > $expectedDays) {
    Alert::create([
        'type' => 'shipment_delay',
        'message' => "الشحنة {$shipment->number} مفتوحة منذ {$openDays} يوم",
    ]);
}

// 3. عميل متأخر
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
بناءً على البيانات التالية:
- مبيعات اليوم: {$todaySales}
- مبيعات أمس: {$yesterdaySales}
- متوسط المبيعات: {$avgSales}
- الشحنات المفتوحة: {$openShipments}

قدم 3 رؤى مختصرة بالعربية.
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

## لكل Epic:

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

## قبل البدء في كل Epic:
- [ ] مراجعة المتطلبات
- [ ] مراجعة Edge Cases
- [ ] إنشاء Tests structure أولاً (TDD)
- [ ] مراجعة الـ migrations

## بعد الانتهاء:
- [ ] جميع Tests تمر
- [ ] Documentation محدثة
- [ ] No Breaking Changes
- [ ] Performance OK

---

*آخر تحديث: 2025-12-16*
*الإصدار: 2.0*
