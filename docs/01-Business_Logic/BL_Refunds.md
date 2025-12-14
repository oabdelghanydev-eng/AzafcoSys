# Refunds & Credit Notes - المرتجعات وإشعارات الائتمان

## 📋 نظرة عامة

هذا الملف يوثق كيفية التعامل مع المرتجعات في النظام.

---

## 🔄 أنواع المرتجعات

| النوع | الوصف | التأثير |
|-------|-------|---------|
| **مرتجع قبل التصفية** | البضاعة ترجع للشحنة الأصلية | `remaining_quantity` يزيد |
| **مرتجع بعد التصفية** | البضاعة ترحل للشحنة المفتوحة | `Carryover` جديد |
| **إشعار ائتمان** | تخفيض قيمة الفاتورة | `Credit Note` |

---

## 📊 سيناريو 1: مرتجع قبل التصفية

### الحالة الأصلية:
```
الشحنة #1 (open):
- صنف X: initial=100, sold=30, remaining=70

الفاتورة #5:
- 30 وحدة من صنف X بسعر 10
- total=300, paid=0, balance=300
```

### عند إلغاء الفاتورة:
```
1. invoice.status = 'cancelled'
2. invoice.allocations.delete()
3. shipment_item.remaining_quantity += 30 (يعود لـ 100)
4. customer.balance -= 300
```

### الكود:
```php
// عند إلغاء فاتورة
DB::transaction(function () use ($invoice) {
    // إرجاع الكميات للمخزون
    foreach ($invoice->items as $item) {
        $item->shipmentItem->increment('remaining_quantity', $item->quantity);
        $item->shipmentItem->decrement('sold_quantity', $item->quantity);
    }
    
    // باقي منطق الإلغاء...
});
```

---

## 📊 سيناريو 2: مرتجع بعد التصفية (Late Return)

### الحالة:
```
الشحنة #1 (settled): - مُصفاة
الشحنة #2 (open): - الشحنة الحالية

العميل يُرجع 20 وحدة من صنف X (كانت من شحنة #1)
```

### المنطق:
```
1. لا نُعيد فتح الشحنة المُصفاة
2. نُنشئ Carryover من نوع 'late_return'
3. نُضيف الكمية للشحنة المفتوحة الحالية
```

### الكود:
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
            
            // البحث عن الشحنة المفتوحة الحالية
            $currentOpenShipment = Shipment::where('status', 'open')
                ->orderBy('date', 'desc')
                ->first();
            
            if (!$currentOpenShipment) {
                throw new \Exception("لا توجد شحنة مفتوحة لاستقبال المرتجع");
            }
            
            // البحث عن item موجود أو إنشاء جديد
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
            
            // إنشاء سجل الترحيل
            Carryover::create([
                'from_shipment_id' => $originalShipment->id,
                'from_shipment_item_id' => $originalShipmentItem->id,
                'to_shipment_id' => $currentOpenShipment->id,
                'to_shipment_item_id' => $targetItem->id,
                'product_id' => $item->product_id,
                'quantity' => $returnQuantity,
                'reason' => 'late_return',
                'notes' => "مرتجع من فاتورة #{$invoice->invoice_number}",
                'created_by' => auth()->id(),
            ]);
            
            // إنشاء Credit Note
            $this->createCreditNote($invoice, $item, $returnQuantity);
        });
    }
}
```

---

## 📄 إشعار الائتمان (Credit Note)

### الغرض:
تخفيض قيمة فاتورة موجودة أو إنشاء رصيد دائن للعميل.

### جدول credit_notes (مقترح للمستقبل):
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

## 🔄 MVP Approach (بدون Credit Notes)

في MVP، نتعامل مع المرتجعات كالتالي:

### خيار 1: إلغاء الفاتورة وإنشاء جديدة
```
1. إلغاء الفاتورة الأصلية
2. إرجاع الكميات للمخزون
3. إنشاء فاتورة جديدة بالكميات الصحيحة
```

### خيار 2: تعديل الفاتورة (في نافذة التعديل)
```
1. تقليل الكمية في بند الفاتورة
2. Observer يُحدث المخزون
3. Observer يُحدث رصيد العميل
```

---

## 📊 Decision Table: نوع المرتجع

| الحالة | الشحنة | الإجراء |
|--------|--------|---------|
| فاتورة في نافذة التعديل | أي حالة | تعديل الفاتورة |
| فاتورة خارج النافذة | open | إلغاء + إرجاع للمخزون |
| فاتورة خارج النافذة | closed | إلغاء + إرجاع للمخزون |
| فاتورة خارج النافذة | settled | Late Return + Carryover |

---

## 🔗 Business Rules المرتبطة

- BR-INV-003: إلغاء الفاتورة
- BR-SHP-003: تصفية الشحنة
- BR-FIFO-003: تحديث المتبقي
