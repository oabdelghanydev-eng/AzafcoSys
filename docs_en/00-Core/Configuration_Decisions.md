# Configuration Decisions

## 📋 Overview

This file documents all agreed-upon configuration decisions for the system.

---

## 📊 Business Logic Decisions

### Returns

| Decision | Details |
|--------|----------|
| **Late Return** | ✅ Supported - Carried over to open shipment |
| **Return Impact** | ✅ Increases stock **and** decreases customer balance |
| **Modify Original Invoice** | ❌ Not modified |
| **Separate Table** | ✅ New `returns` table |

### Credit Notes
| Decision | Details |
|--------|----------|
| **Separate System** | ❌ Not needed for MVP |
| **Alternative** | Return decreases customer balance directly |

### Discounts
| Decision | Details |
|--------|----------|
| **Maximum** | `discount <= subtotal` |
| **Zero Invoice** | ❌ Forbidden |

---

## 🔐 Security Decisions

| Decision | Details |
|--------|----------|
| **Two-Factor Auth** | ❌ Not needed - Google OAuth is sufficient |
| **Password Login** | ✅ Required for users without Google |
| **Session Duration** | ♾️ Does not expire until Logout |

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

| Decision | Details |
|--------|----------|
| **Cache Driver** | `file` (Hostinger does not support Redis) |
| **Expected Volume** | Less than 50 invoices/day |
| **Scaling** | Not required now |

---

## 🚀 DevOps Decisions

| Decision | Details |
|--------|----------|
| **Source Control** | GitHub ✅ |
| **CI/CD** | GitHub Actions ✅ |
| **Staging** | ❌ No - Local + Production only |
| **Notifications** | Telegram 📱 |

---

## 🎨 UX/Frontend Decisions

| Decision | Details |
|--------|----------|
| **Offline Support** | ❌ No - Internet required |
| **Languages** | 🌐 Arabic + English |
| **Printing** | 🖨️ PDF only |

---

## 📦 New Returns Table

```sql
CREATE TABLE returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    return_number VARCHAR(50) NOT NULL UNIQUE,
    
    -- Customer and Original Invoice (Optional)
    customer_id BIGINT UNSIGNED NOT NULL,
    original_invoice_id BIGINT UNSIGNED NULL,
    
    -- Details
    date DATE NOT NULL,
    
    -- Financial Impact
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    
    -- Status
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
    
    -- Return Source
    original_invoice_item_id BIGINT UNSIGNED NULL,
    target_shipment_item_id BIGINT UNSIGNED NOT NULL,
    
    -- Quantity and Price
    quantity DECIMAL(10,3) NOT NULL,
    price_per_kg DECIMAL(10,2) NOT NULL COMMENT 'Price per KG - consistent with invoice_items',
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
            // 1. Decrease Customer Balance
            $return->customer->decrement('balance', $return->total_amount);
            
            // 2. Increase Inventory
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

## 📊 Tables Summary

| Type | Tables |
|--------|---------|
| **Original** | 20 tables |
| **New** | +2 (returns, return_items) |
| **Total** | 22 tables |
