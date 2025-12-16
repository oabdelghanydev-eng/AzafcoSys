# Shipment Business Logic - منطق الشحنات

## 📋 نظرة عامة

الشحنات هي **المصدر الرئيسي للمخزون** في النظام.
كل شحنة تأتي من مورد وتحتوي على أصناف متعددة.

---

## 🔄 دورة حياة الشحنة (Lifecycle)

```
┌─────────────┐
│    open     │ ← الحالة الافتراضية عند الإنشاء
└──────┬──────┘
       │
       │ بيع كل الكميات
       ▼
┌─────────────┐
│   closed    │ ← الشحنة نفدت (تلقائي)
└──────┬──────┘
       │
       │ تصفية + ترحيل المتبقي
       ▼
┌─────────────┐
│  settled    │ ← تمت التصفية النهائية
└──────┬──────┘
       │
       │ Unsettle (نادر)
       ▼
┌─────────────┐
│   closed    │ ← إعادة فتح
└─────────────┘
```

---

## 📊 Decision Table: تغيير الحالة

| من | إلى | الشرط | الآلية |
|----|-----|-------|--------|
| open | closed | remaining = 0 | تلقائي (Observer) |
| open | settled | يدوي بالأمر | settle() |
| closed | settled | يدوي بالأمر | settle() |
| settled | closed | Unsettle + safety check | unsettle() |
| open | open | — | لا تغيير |

---

## 🧮 الحسابات (Calculations)

### 1. حساب المتبقي الإجمالي

```
total_remaining = SUM(shipment_items.remaining_quantity)

إذا total_remaining = 0:
  status → 'closed'
```

### 2. حساب تصفية الشحنة

```
لكل صنف في الشحنة:
┌────────────────────────────────────────────────┐
│ initial_quantity    = الكمية الأصلية           │
│ sold_quantity       = المباع                   │
│ wastage_quantity    = الهالك                   │
│ returned_quantity   = المرتجع                  │
│ carryover_in        = مُرحل إليها              │
│ carryover_out       = مُرحل منها               │
│ remaining_quantity  = المتبقي                  │
│                                               │
│ التحقق:                                        │
│ initial + carryover_in - sold - wastage       │
│ - carryover_out = remaining                   │
└────────────────────────────────────────────────┘
```

### 3. الترحيل (Carryover)

```
عند تصفية شحنة بها متبقي:

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
    'number' => 'required|unique:shipments,number',
    'date' => 'required|date',
    'arrival_date' => 'nullable|date|after_or_equal:date',
    'items' => 'required|array|min:1',
    'items.*.product_id' => 'required|exists:products,id',
    'items.*.cartons' => 'required|integer|min:1',
    'items.*.weight_per_unit' => 'required|numeric|min:0.001',
    'items.*.initial_quantity' => 'required|numeric|min:0.001',
    'items.*.weight_label' => 'nullable|string|max:50',
    'notes' => 'nullable|string|max:1000',
]
```

### SettleShipmentRequest

```php
[
    'next_shipment_id' => 'required|exists:shipments,id',
    // الشحنة التالية يجب أن تكون مفتوحة
    // Custom validation
]
```

### UpdateShipmentRequest (2025-12-16)

```php
// ✅ قواعد التحديث:
// - فقط الشحنات المفتوحة يمكن تعديلها
// - لا يمكن تقليل الكمية أقل من المباع

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
        
        // لا يمكن تعديل شحنة مُصفاة (عدا الحالة)
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
     * PURPOSE: إغلاق الشحنة تلقائياً عند نفاد الكمية
     */
    public function updated(ShipmentItem $item): void
    {
        // فقط عند تغير remaining_quantity
        if (!$item->wasChanged('remaining_quantity')) {
            return;
        }
        
        // فقط عندما يصبح صفر
        if ($item->remaining_quantity != 0) {
            return;
        }
        
        // حساب إجمالي المتبقي في الشحنة
        $totalRemaining = $item->shipment
            ->items()
            ->sum('remaining_quantity');
        
        // إذا الشحنة فارغة تماماً
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
     * PURPOSE: منع حذف شحنة لها فواتير
     */
    public function deleting(Shipment $shipment): bool
    {
        $hasInvoices = InvoiceItem::whereIn(
            'shipment_item_id',
            $shipment->items->pluck('id')
        )->exists();
        
        if ($hasInvoices) {
            throw new \Exception("لا يمكن حذف شحنة لها فواتير مرتبطة");
        }
        
        return true;
    }
    
    /**
     * EVENT: updating
     * PURPOSE: حماية الشحنة المُصفاة
     */
    public function updating(Shipment $shipment): bool
    {
        if ($shipment->getOriginal('status') === 'settled') {
            // السماح فقط بتغيير status
            $changedFields = array_keys($shipment->getDirty());
            $allowedFields = ['status', 'updated_at'];
            
            $forbidden = array_diff($changedFields, $allowedFields);
            
            if (!empty($forbidden)) {
                throw new \Exception(
                    "لا يمكن تعديل شحنة مُصفاة: " . implode(', ', $forbidden)
                );
            }
        }
        
        return true;
    }
    
    /**
     * EVENT: updated
     * PURPOSE: معالجة Unsettle
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
     * استرجاع الترحيلات
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
                        "لا يمكن إلغاء التصفية! " .
                        "الكمية المرحلة تم بيعها من الشحنة التالية"
                    );
                }
                
                // استرجاع للشحنة الأصلية
                $carryover->fromShipmentItem->increment(
                    'remaining_quantity',
                    $carryover->quantity
                );
                
                // خصم من الشحنة التالية
                $nextItem->decrement('initial_quantity', $carryover->quantity);
                $nextItem->decrement('remaining_quantity', $carryover->quantity);
                
                // حذف item إذا فارغ
                if ($nextItem->initial_quantity <= 0) {
                    $nextItem->delete();
                }
                
                // حذف سجل الترحيل
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
     * تصفية الشحنة
     */
    public function settle(Shipment $shipment, Shipment $nextShipment): void
    {
        if ($shipment->status === 'settled') {
            throw new \Exception("الشحنة مُصفاة بالفعل");
        }
        
        if ($nextShipment->status !== 'open') {
            throw new \Exception("الشحنة التالية يجب أن تكون مفتوحة");
        }
        
        DB::transaction(function () use ($shipment, $nextShipment) {
            // جلب الأصناف ذات المتبقي
            $itemsWithRemaining = $shipment->items()
                ->where('remaining_quantity', '>', 0)
                ->get();
            
            foreach ($itemsWithRemaining as $item) {
                // إنشاء item في الشحنة التالية
                $newItem = ShipmentItem::create([
                    'shipment_id' => $nextShipment->id,
                    'product_id' => $item->product_id,
                    'weight_label' => $item->weight_label,
                    'weight_per_unit' => $item->weight_per_unit,
                    'cartons' => $item->cartons, // تقريبي
                    'initial_quantity' => $item->remaining_quantity,
                    'remaining_quantity' => $item->remaining_quantity,
                    'carryover_in_quantity' => $item->remaining_quantity,
                ]);
                
                // إنشاء سجل الترحيل
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
                
                // تحديث الـ item الأصلي
                $item->update([
                    'carryover_out_quantity' => $item->remaining_quantity,
                    'remaining_quantity' => 0,
                ]);
            }
            
            // تغيير حالة الشحنة
            $shipment->update([
                'status' => 'settled',
                'settled_at' => now(),
            ]);
        });
    }
    
    /**
     * إنشاء تقرير التصفية
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

## 📊 Flowchart: تصفية الشحنة

```
┌─────────────┐
│   البداية   │
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
│ المتابعة │    │ "الشحنة مُصفاة"   │
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
│ المتابعة │    │ "يجب اختيار شحنة    │
└────┬────┘    │  مفتوحة"             │
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

### 1. تصفية شحنة فارغة

```
الحالة: كل الـ remaining = 0

النتيجة:
  - لا يوجد ترحيل
  - status → 'settled'
  - تقرير بدون carryovers
```

### 2. Unsettle بعد بيع المرحل

```
الحالة:
  - رحلنا 100 كيلو للشحنة التالية
  - الشحنة التالية باعت 60

محاولة: Unsettle

الفحص:
  nextItem.remaining (40) < carryover.quantity (100)

النتيجة:
  throw Exception("لا يمكن إلغاء التصفية")
```

### 3. حذف شحنة لها ترحيلات واردة

```
الحالة:
  - شحنة B لها carryover_in من شحنة A
  - محاولة حذف شحنة B

النتيجة:
  - يُفحص InvoiceItems
  - إذا لا يوجد فواتير: الحذف مسموح
  - Carryover سيُحذف (CASCADE)
```

### 4. مرتجع بعد التصفية

```
الحالة:
  - شحنة مُصفاة
  - العميل يرجع بضاعة من هذه الشحنة

المعالجة: (late_return)
  - CREATE Carryover(reason = 'late_return')
  - إضافة للشحنة المفتوحة الحالية
  - لا يُعاد فتح الشحنة المُصفاة
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
INDEX idx_product_remaining (product_id, remaining_quantity) -- للـ FIFO
```

---

## 🔗 القواعد المرتبطة

- BR-SHP-001 إلى BR-SHP-007
- BR-FIFO-001 إلى BR-FIFO-003
