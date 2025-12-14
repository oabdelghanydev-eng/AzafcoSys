# Configuration Decisions - قرارات الإعداد

## 📋 نظرة عامة

هذا الملف يوثق جميع القرارات التكوينية للنظام المُتفق عليها.

---

## 📊 Business Logic Decisions

### المرتجعات (Returns)

| القرار | التفاصيل |
|--------|----------|
| **Late Return** | ✅ مدعوم - ترحل للشحنة المفتوحة |
| **تأثير المرتجع** | ✅ يزود المخزون **و** يخفض رصيد العميل |
| **تعديل الفاتورة الأصلية** | ❌ لا يتم تعديلها |
| **جدول منفصل** | ✅ جدول `returns` جديد |

### Credit Notes
| القرار | التفاصيل |
|--------|----------|
| **نظام منفصل** | ❌ لا حاجة للـ MVP |
| **البديل** | المرتجع يخفض رصيد العميل مباشرة |

### الخصومات
| القرار | التفاصيل |
|--------|----------|
| **الحد الأقصى** | `discount <= subtotal` |
| **فاتورة بصفر** | ❌ ممنوع |

---

## 🔐 Security Decisions

| القرار | التفاصيل |
|--------|----------|
| **Two-Factor Auth** | ❌ لا حاجة - Google OAuth كافي |
| **Password Login** | ✅ مطلوب للمستخدمين بدون Google |
| **Session Duration** | ♾️ لا تنتهي إلا بـ Logout |

### Password Policy
```php
'password' => [
    'required',
    'min:8',
    'regex:/[a-z]/',      // lowercase
    'regex:/[A-Z]/',      // uppercase
    'regex:/[0-9]/',      // number
]
```

---

## ⚡ Performance Decisions

| القرار | التفاصيل |
|--------|----------|
| **Cache Driver** | `file` (Hostinger لا يدعم Redis) |
| **الحجم المتوقع** | أقل من 50 فاتورة/يوم |
| **Scaling** | غير مطلوب الآن |

---

## 🚀 DevOps Decisions

| القرار | التفاصيل |
|--------|----------|
| **Source Control** | GitHub ✅ |
| **CI/CD** | GitHub Actions ✅ |
| **Staging** | ❌ لا - Local + Production فقط |
| **Notifications** | Telegram 📱 |

---

## 🎨 UX/Frontend Decisions

| القرار | التفاصيل |
|--------|----------|
| **Offline Support** | ❌ لا - يحتاج إنترنت |
| **Languages** | 🌐 عربي + English |
| **Printing** | 🖨️ PDF فقط |

---

## 📦 جدول المرتجعات الجديد

```sql
CREATE TABLE returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    return_number VARCHAR(50) NOT NULL UNIQUE,
    
    -- العميل والفاتورة الأصلية (اختياري)
    customer_id BIGINT UNSIGNED NOT NULL,
    original_invoice_id BIGINT UNSIGNED NULL,
    
    -- التفاصيل
    date DATE NOT NULL,
    
    -- التأثير المالي
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    
    -- الحالة
    status ENUM('active', 'cancelled') DEFAULT 'active',
    
    notes TEXT NULL,
    
    -- Metadata
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (original_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    
    INDEX idx_customer (customer_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    return_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    
    -- مصدر المرتجع
    original_invoice_item_id BIGINT UNSIGNED NULL,
    target_shipment_item_id BIGINT UNSIGNED NOT NULL,
    
    -- الكمية والسعر
    quantity DECIMAL(10,3) NOT NULL,
    price_per_kg DECIMAL(10,2) NOT NULL COMMENT 'سعر الكيلو - متسق مع invoice_items',
    subtotal DECIMAL(15,2) NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (original_invoice_item_id) REFERENCES invoice_items(id) ON DELETE SET NULL,
    FOREIGN KEY (target_shipment_item_id) REFERENCES shipment_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔄 Return Observer Logic

```php
class ReturnObserver
{
    public function created(Return $return): void
    {
        DB::transaction(function () use ($return) {
            // 1. تقليل رصيد العميل
            $return->customer->decrement('balance', $return->total_amount);
            
            // 2. زيادة المخزون
            foreach ($return->items as $item) {
                $item->targetShipmentItem->increment('remaining_quantity', $item->quantity);
            }
            
            // 3. Audit Log
            AuditLog::create([...]);
        });
    }
}
```

---

## 📊 ملخص الجداول

| الجملة | الجداول |
|--------|---------|
| **الأصلية** | 20 جدول |
| **الجديدة** | +2 (returns, return_items) |
| **الإجمالي** | 22 جدول |
