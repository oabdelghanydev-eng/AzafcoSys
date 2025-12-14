# Inventory FIFO Business Logic - منطق المخزون

## 📋 نظرة عامة

FIFO = **F**irst **I**n, **F**irst **O**ut
البيع من أقدم شحنة أولاً لضمان دوران المخزون.

---

## 🔄 كيف يعمل FIFO؟

```
المخزون:
┌──────────────────────────────────────────────┐
│ Shipment #1 (10 Dec) - 50 kg remaining       │ ← الأقدم
│ Shipment #2 (15 Dec) - 100 kg remaining      │
│ Shipment #3 (20 Dec) - 75 kg remaining       │ ← الأحدث
└──────────────────────────────────────────────┘

طلب بيع: 80 kg

FIFO Allocation:
┌──────────────────────────────────────────────┐
│ من Shipment #1: 50 kg (نفد)                  │
│ من Shipment #2: 30 kg (باقي 70)              │
└──────────────────────────────────────────────┘

النتيجة:
  invoice_items: 2 بنود
  - item 1: shipment_item_id=#1, qty=50
  - item 2: shipment_item_id=#2, qty=30
```

---

## 🧮 الخوارزمية

### FifoAllocatorService

```php
<?php

namespace App\Services;

use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;

class FifoAllocatorService
{
    /**
     * تخصيص كمية من المخزون لمنتج معين
     * 
     * @param int $productId المنتج
     * @param float $quantity الكمية المطلوبة
     * @return array مصفوفة التخصيصات
     * @throws \Exception إذا الكمية غير متوفرة
     */
    public function allocate(int $productId, float $quantity): array
    {
        return DB::transaction(function () use ($productId, $quantity) {
            $remaining = $quantity;
            $allocations = [];
            
            // جلب الأصناف المتوفرة (الأقدم أولاً حسب fifo_sequence)
            // Best Practice 2025-12-13:
            // - fifo_sequence: للقرارات المحاسبية (غير قابل للتعديل)
            // - date: للتقارير فقط
            $availableItems = ShipmentItem::where('product_id', $productId)
                ->where('remaining_quantity', '>', 0)
                ->whereHas('shipment', function ($q) {
                    $q->whereIn('status', ['open', 'closed']);
                })
                ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
                ->orderBy('shipments.fifo_sequence', 'asc') // FIFO: حسب التسلسل
                ->orderBy('shipment_items.id', 'asc')
                ->select('shipment_items.*')
                ->lockForUpdate() // حماية Race Condition
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
                
                // تحديث المتبقي
                $item->decrement('remaining_quantity', $allocateQty);
                $item->increment('sold_quantity', $allocateQty);
                
                $remaining -= $allocateQty;
            }
            
            // فحص التوفر
            if ($remaining > 0) {
                throw new \Exception(
                    "الكمية المطلوبة ({$quantity}) غير متوفرة. " .
                    "المتوفر: " . ($quantity - $remaining)
                );
            }
            
            return $allocations;
        });
    }
    
    /**
     * التحقق من توفر الكمية (بدون خصم)
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
     * الحصول على الكمية المتوفرة
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
     * حساب تكلفة الوحدة
     */
    private function calculateUnitCost(ShipmentItem $item): float
    {
        // يمكن حسابها من سعر الشحنة / الكمية
        return 0; // للبساطة الآن
    }
    
    /**
     * إلغاء التخصيص (عند إلغاء/حذف فاتورة)
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

| الحالة | الشرط | النتيجة |
|--------|-------|---------|
| الكمية متوفرة في شحنة واحدة | required ≤ item.remaining | allocation واحد |
| الكمية تحتاج شحنات متعددة | required > item.remaining | allocations متعددة |
| الكمية غير متوفرة | SUM(remaining) < required | Exception |
| المنتج غير موجود | product_id invalid | Exception |
| شحنة مُصفاة | status = 'settled' | يتم تجاوزها |

---

## 🔄 Flowchart: FIFO Allocation

```
┌─────────────┐
│   البداية   │
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
  نعم             لا
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
   نعم             لا
    │               │
    ▼               ▼
┌──────────────┐ ┌──────────────┐
│   Exception  │ │   RETURN     │
│ "غير متوفر"  │ │ allocations  │
└──────────────┘ └──────────────┘
```

---

## ⚠️ Edge Cases

### 1. بيع أكثر من المتوفر

```
المتوفر: 50 kg
المطلوب: 80 kg

النتيجة: Exception
الرسالة: "الكمية المطلوبة (80) غير متوفرة. المتوفر: 50"
```

### 2. شحنة تنفد أثناء البيع

```
الحالة:
  - Shipment #1: 10 kg
  - Shipment #2: 100 kg
  - مطلوب: 15 kg

FIFO:
  - 10 من #1 (نفد)
  - 5 من #2

ShipmentItemObserver:
  - يكتشف أن #1 remaining = 0
  - يفحص الشحنة: هل كلها نفدت؟
  - إذا نعم: status → 'closed'
```

### 3. Race Condition

```
المشكلة:
  - User A: يبيع 50 من المخزون
  - User B: يبيع 30 من نفس المخزون
  - نفس اللحظة

الحماية:
  lockForUpdate() على ShipmentItems

النتيجة:
  - User A يقفل الصفوف
  - User B ينتظر
  - User A يكمل
  - User B يحصل على البيانات المحدثة
```

### 4. إلغاء فاتورة

```
الحالة:
  - فاتورة ببندين من شحنتين مختلفتين
  - item 1: 30 kg من shipment #1
  - item 2: 20 kg من shipment #2

عند الإلغاء:
  FifoAllocatorService::deallocate([
    ['shipment_item_id' => 1, 'quantity' => 30],
    ['shipment_item_id' => 2, 'quantity' => 20],
  ])

النتيجة:
  - shipment_item #1: remaining += 30, sold -= 30
  - shipment_item #2: remaining += 20, sold -= 20
```

---

## 📈 تتبع المخزون

### تقرير المخزون الحالي

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

## 🔗 القواعد المرتبطة

- BR-FIFO-001: تخصيص الكمية
- BR-FIFO-002: تتبع المصدر
- BR-FIFO-003: تحديث المتبقي
