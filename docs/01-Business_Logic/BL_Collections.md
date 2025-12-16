# Collection Business Logic - منطق التحصيلات

## 📋 نظرة عامة

التحصيلات هي عملية **دفع العميل** لسداد فواتيره.
النظام يدعم التوزيع التلقائي (FIFO) والتوزيع اليدوي.

---

## 🔄 دورة حياة التحصيل (Lifecycle)

```
┌─────────────┐
│   إنشاء     │
│ التحصيل    │
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
│ توزيع على الفواتير      │
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

## 📊 Decision Table: التوزيع

| الحالة | distribution_method | السلوك |
|--------|---------------------|--------|
| FIFO الافتراضي | oldest_first | الأقدم أولاً |
| LIFO | newest_first | الأحدث أولاً |
| يدوي | manual | ربط بفاتورة محددة |
| مبلغ > الديون | أي | الفائض = رصيد دائن |

---

## 🧮 الحسابات (Calculations)

### 1. توزيع FIFO

```
Input:
  - customer_id
  - amount (المبلغ المحصل)

Algorithm:
  remaining = amount
  invoices = GET unpaid invoices ORDER BY date ASC
  
  FOR EACH invoice IN invoices:
    IF remaining <= 0: BREAK
    
    allocate = MIN(remaining, invoice.balance)
    
    CREATE CollectionAllocation(invoice, allocate)
    
    remaining -= allocate
  
  IF remaining > 0:
    // الفائض = رصيد دائن للعميل
    // customer.balance أصبح سالب تلقائياً
```

### 2. تأثير التحصيل على الأرصدة

```
عند إنشاء تحصيل:
┌─────────────────────────────────────────────────┐
│ customer.balance -= collection.amount           │
│                                                 │
│ لكل allocation:                                 │
│   invoice.paid_amount += allocation.amount      │
│   invoice.balance -= allocation.amount          │
└─────────────────────────────────────────────────┘

النتائج المحتملة لرصيد العميل:
┌──────────────────────────────────┐
│ + موجب = لا يزال مديون         │
│ 0 صفر = سدد كل ديونه           │
│ - سالب = له رصيد دائن (زائد)   │
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
// في حالة التوزيع اليدوي: الفاتورة يجب أن تكون للعميل نفسه
if ($this->distribution_method === 'manual') {
    $invoice = Invoice::find($this->invoice_id);
    if ($invoice->customer_id !== $this->customer_id) {
        $fail('الفاتورة لا تخص هذا العميل');
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
        
        // نافذة التعديل
        $editDays = (int) Setting::get('edit_window_days', 1);
        return $collection->date >= now()->subDays($editDays)->startOfDay()
            && $collection->status === 'confirmed';
    }
    
    /**
     * هل يمكن إلغاء التحصيل؟
     * ⚠️ تصحيح 2025-12-13: الإلغاء بدلاً من الحذف
     */
    public function cancel(User $user, Collection $collection): bool
    {
        return $user->hasPermission('cancel_collections')
            && $collection->status === 'confirmed';
    }
    
    // ❌ لا يوجد delete() - الحذف ممنوع نهائياً
    // استخدم الإلغاء (cancel) بدلاً من الحذف للحفاظ على سجل المراجعة
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
        // تقليل رصيد العميل
        $collection->customer->decrement('balance', $collection->amount);
        
        // التوزيع يتم عبر CollectionService
    }
    
    /**
     * EVENT: updated
     * PURPOSE: معالجة الإلغاء
     * ⚠️ تصحيح 2025-12-13: إلغاء بدلاً من حذف
     */
    public function updated(Collection $collection): void
    {
        if ($collection->wasChanged('status')) {
            $oldStatus = $collection->getOriginal('status');
            $newStatus = $collection->status;
            
            // إلغاء التحصيل
            if ($oldStatus === 'confirmed' && $newStatus === 'cancelled') {
                // زيادة رصيد العميل
                $collection->customer->increment('balance', $collection->amount);
                
                // حذف الـ Allocations
                $collection->allocations()->delete();
                // Observers ستُرجع balances للفواتير
            }
            
            // منع إعادة التفعيل
            if ($oldStatus === 'cancelled' && $newStatus === 'confirmed') {
                throw new BusinessException('COL_002', 'لا يمكن إعادة تفعيل تحصيل ملغى');
            }
        }
    }
    
    /**
     * EVENT: deleting
     * PURPOSE: ❌ منع الحذف نهائياً
     * ⚠️ تصحيح 2025-12-13
     */
    public function deleting(Collection $collection): bool
    {
        throw new BusinessException(
            'COL_001',
            "لا يمكن حذف التحصيلات. استخدم الإلغاء بدلاً من الحذف للحفاظ على سجل المراجعة."
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
     * عند تخصيص مبلغ لفاتورة
     */
    public function created(CollectionAllocation $allocation): void
    {
        $invoice = $allocation->invoice;
        
        // زيادة المدفوع
        $invoice->increment('paid_amount', $allocation->amount);
        
        // تقليل الرصيد
        $invoice->decrement('balance', $allocation->amount);
    }
    
    /**
     * EVENT: deleted
     * عند حذف التخصيص (عكس العملية)
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

## 🛠️ CollectionService - التفصيل الكامل

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
     * توزيع مبلغ التحصيل على الفواتير
     * 
     * @param Collection $collection التحصيل المراد توزيعه
     * @throws \Exception إذا فشلت العملية
     */
    public function allocatePayment(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            $remaining = $collection->amount;
            
            // تحديد ترتيب الفواتير
            $order = $collection->distribution_method === 'newest_first' 
                ? 'desc' 
                : 'asc';
            
            // جلب الفواتير غير المسددة مع قفل للحماية من Race Condition
            $unpaidInvoices = Invoice::where('customer_id', $collection->customer_id)
                ->where('balance', '>', 0)
                ->where('status', 'active')
                ->orderBy('date', $order)
                ->lockForUpdate()
                ->get();
            
            foreach ($unpaidInvoices as $invoice) {
                if ($remaining <= 0) break;
                
                $allocateAmount = min($remaining, $invoice->balance);
                
                // إنشاء سجل التوزيع
                // Observer سيتولى تحديث الفاتورة
                CollectionAllocation::create([
                    'collection_id' => $collection->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                ]);
                
                $remaining -= $allocateAmount;
            }
            
            // لو تبقى مبلغ، يصبح رصيد دائن للعميل
            // (customer.balance سالب تلقائياً من CollectionObserver)
        });
    }
    
    /**
     * التوزيع اليدوي على فاتورة محددة
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
            
            // الفائض يبقى كرصيد دائن
        });
    }
    
    /**
     * إلغاء توزيع التحصيل
     */
    public function reverseAllocations(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            // Observers ستتولى تحديث الفواتير
            $collection->allocations()->delete();
        });
    }
}
```

---

## 📊 Flowchart: إنشاء تحصيل

```
┌─────────────┐
│   البداية   │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│ Validation Request       │
└───────────┬─────────────┘
            │
    ┌───────┴───────┐
    │               │
   ✅              ❌
    │               │
    ▼               ▼
┌─────────┐    ┌──────────┐
│ المتابعة │    │ 422 Error│
└────┬────┘    └──────────┘
     │
     ▼
┌─────────────────────────┐
│ DB::transaction START   │
└───────────┬─────────────┘
     │
     ▼
┌─────────────────────────┐
│   إنشاء Collection      │
│   - توليد receipt_number│
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
│ distribution_method?               │
├──────────────┬─────────────────────┤
│ oldest_first │ newest_first│manual │
└──────┬───────┴──────┬──────┴───┬───┘
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

### 1. تحصيل أكبر من إجمالي الديون

```
الحالة:
  - customer.balance = 500 (مديون)
  - collection.amount = 700

النتيجة:
  - توزيع 500 على الفواتير
  - customer.balance = -200 (رصيد دائن)
  - remaining = 200 (لا يُنشأ له allocation)
```

### 2. العميل ليس له ديون

```
الحالة:
  - customer.balance = 0
  - collection.amount = 100

النتيجة:
  - لا توجد فواتير للتوزيع
  - customer.balance = -100 (رصيد دائن)
```

### 3. حذف تحصيل

```
الحالة:
  - collection.amount = 500
  - موزع على 3 فواتير

الإجراء:
  1. CASCADE DELETE على allocations
  2. كل allocation يُفعّل deleted event
  3. كل فاتورة تُرجع لها الـ balance
  4. customer.balance += 500
```

### 4. Race Condition - تحصيلين متزامنين

```
المشكلة:
  User A: يحصل 500
  User B: يحصل 300
  نفس العميل، نفس اللحظة

الحماية:
  lockForUpdate() على الفواتير
  DB::transaction

النتيجة:
  User A يكمل أولاً
  User B ينتظر ثم يكمل
  التوزيع صحيح
```

---

## 📈 Performance Considerations

### Indexes المطلوبة

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
// ✅ صحيح
$collections = Collection::with([
    'customer',
    'allocations.invoice',
    'createdBy'
])->get();
```

---

## 🔗 القواعد المرتبطة

- BR-COL-001 إلى BR-COL-005
- BR-INV-002 (تحديث رصيد الفاتورة)
- BR-CUS-003 (تقليل رصيد العميل)
