# تقرير تعديلات 2025-12-19

## ملخص التعديلات

### 1. تغيير أسماء الحقول في API الفواتير

**الحقول الجديدة:**
| الحقل | النوع | الوصف |
|-------|------|-------|
| `cartons` | integer, required | عدد الكراتين المباعة |
| `total_weight` | numeric, required | الوزن الكلي للبيع (من الميزان) |
| `price` | numeric, required | سعر الكيلو |

**مثال الـ Request:**
```json
{
  "customer_id": 1,
  "date": "2025-12-19",
  "items": [{
    "product_id": 1,
    "cartons": 3,
    "total_weight": 73.0,
    "price": 50.0
  }]
}
```

### 2. تعديل التقرير اليومي

- **جدول الفواتير**: يظهر الكراتين + وزن الوحدة + سعر الكيلو + المبلغ
- **المخزون المتبقي**: يظهر الكراتين المتبقية × وزن الوحدة = الوزن المتوقع
- **العجز اليومي**: (كراتين × وزن الوحدة) - الوزن الفعلي

### 3. الملفات المعدلة

- `app/Http/Requests/Api/StoreInvoiceRequest.php`
- `app/Http/Controllers/Api/InvoiceController.php`
- `app/Services/Reports/DailyClosingReportService.php`
- `resources/views/reports/daily-closing.blade.php`
- 5 ملفات اختبار

---

## الاختبارات

```
Tests: 171 passed, 3 skipped, 0 failed ✅
```

---

## ملاحظة للمراجعة المستقبلية

التعامل مع الشحنات يكون عبر **عدد الكراتين**:
- لا يمكن بيع كراتين أكثر من المتاح في الشحنة
- إذا أراد بيع أكثر، يجب ترحيل وإضافة بند من شحنة أخرى
- العجز يُحسب عند تصفية الشحنة: وزن الوارد - وزن المباع
