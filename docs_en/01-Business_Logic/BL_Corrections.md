# Soft-Correction Flow - Correction Logic

## 📋 Overview

The smart correction system maintains:
- **Data Integrity** - No deletion, no direct editing
- **Audit Trail** - Every correction is documented
- **Maker-Checker** - Corrections require approval
- **Accounting Balance** - Balanced reversal entries

---

## 🔄 Correction Types

### 1️⃣ Invoice Correction

```
Original Invoice #1001:      Correction Invoice #1001-C1:
┌────────────────────┐       ┌─────────────────────────┐
│ Total: 1000        │  ──▶  │ Type: adjustment        │
│ Product: A         │       │ Original Ref: #1001     │
│ Status: active     │       │ Adjustment: -200        │
│                    │       │ Reason: Price Error     │
└────────────────────┘       │ Status: pending → approved│
                             └─────────────────────────┘
```

### 2️⃣ Collection Correction

```
Original Collection #501:    Correction Collection #501-C1:
┌────────────────────┐       ┌─────────────────────────┐
│ Amount: 500        │  ──▶  │ Amount: -50 (refund)    │
│ Method: cash       │       │ Original Ref: #501      │
│ Customer: X        │       │ Reason: Refund Excess   │
└────────────────────┘       └─────────────────────────┘
```

### 3️⃣ Inventory Adjustment

```
Inventory Adjustment #ADJ-20251213-0001:
┌────────────────────────────────────────────┐
│ Type: damage                               │
│ Product: A (Shipment #5)                   │
│ Before: 500 kg                             │
│ After: 480 kg                              │
│ Change: -20 kg                             │
│ Reason: Damaged - Water                    │
│ Status: pending → approved                 │
│ Cost Impact: -200 EGP                      │
└────────────────────────────────────────────┘
```

---

## 🔐 Maker-Checker Workflow

```
┌─────────────────┐        ┌─────────────────┐        ┌─────────────────┐
│     MAKER       │───────▶│    PENDING      │───────▶│    CHECKER      │
│  (Creator)      │        │   (Waiting)     │        │  (Approver)     │
│                 │        │                 │        │                 │
│  Creates        │        │  Correction     │        │  Approves OR    │
│  Correction     │        │  waiting for    │        │  Rejects        │
│                 │        │  approval       │        │                 │
└─────────────────┘        └─────────────────┘        └────────┬────────┘
                                                              │
                           ┌─────────────────┐                │
                           │    APPLIED      │◀───────────────┘
                           │                 │   (If Approved)
                           │  Changes take   │
                           │  effect         │
                           └─────────────────┘
```

> ⚠️ **Important Rule:** Maker cannot approve their own correction

---

## 📊 Database Schema

### corrections Table

```sql
CREATE TABLE corrections (
    id BIGINT PRIMARY KEY,
    correctable_type VARCHAR(100),  -- 'Invoice', 'Collection'
    correctable_id BIGINT,
    correction_type ENUM('adjustment', 'reversal', 'reallocation'),
    
    original_value DECIMAL(15,2),
    adjustment_value DECIMAL(15,2),  -- Can be negative
    new_value DECIMAL(15,2),
    
    reason TEXT,
    reason_code VARCHAR(50),
    
    correction_sequence INT,  -- 1, 2, 3 for same record
    
    status ENUM('pending', 'approved', 'rejected'),
    created_by BIGINT,
    approved_by BIGINT,
    approved_at TIMESTAMP,
    rejection_reason TEXT
);
```

### inventory_adjustments Table

```sql
CREATE TABLE inventory_adjustments (
    id BIGINT PRIMARY KEY,
    adjustment_number VARCHAR(50) UNIQUE,
    
    shipment_item_id BIGINT,
    product_id BIGINT,
    
    quantity_before DECIMAL(15,3),
    quantity_after DECIMAL(15,3),
    quantity_change DECIMAL(15,3),
    
    adjustment_type ENUM('physical_count', 'damage', 'theft', 'error', 'transfer', 'expiry'),
    reason TEXT,
    
    unit_cost DECIMAL(15,2),
    total_cost_impact DECIMAL(15,2),
    
    status ENUM('pending', 'approved', 'rejected'),
    created_by BIGINT,
    approved_by BIGINT,
    approved_at TIMESTAMP
);
```

---

## 🧮 Services

### CorrectionService

```php
// Create invoice correction (pending)
$correction = $correctionService->createInvoiceCorrection(
    $invoice,
    -200,  // Negative = credit note
    'Price was incorrect',
    'PRICE_ERROR'
);

// Approve (by different user)
$correctionInvoice = $correctionService->approveInvoiceCorrection(
    $correction['correction'],
    $approver
);

// Create collection refund (pending)
$correction = $correctionService->createCollectionCorrection(
    $collection,
    -50,  // Negative = refund
    'Refund excess amount'
);
```

### InventoryAdjustmentService

```php
// Create adjustment (pending)
$adjustment = $adjustmentService->createAdjustment(
    $shipmentItemId,
    480,  // New quantity
    'damage',
    'Damaged by water'
);

// Approve (by different user)
$adjustmentService->approve($adjustment, $approver);

// Get pending for approval dashboard
$pending = $adjustmentService->getPendingAdjustments();
```

---

## 🔗 Error Codes

| Code | Arabic | English |
|------|--------|---------|
| COR_001 | التصحيح ليس في انتظار الموافقة | Not pending approval |
| COR_002 | لا يمكنك الموافقة على تصحيحك الخاص | Cannot approve own correction |
| COR_003 | نوع التصحيح غير صالح | Invalid correction type |
| ADJ_001 | لا يمكن تعديل مخزون شحنة مُصفاة | Cannot adjust settled shipment |
| ADJ_002 | الكمية لا يمكن أن تكون سالبة | Quantity cannot be negative |
| ADJ_003 | لا يمكن تقليل الكمية لأقل من المباع | Cannot reduce below sold |
| ADJ_004 | التسوية ليست في انتظار الموافقة | Adjustment not pending |
| ADJ_005 | لا يمكنك الموافقة على تسويتك الخاصة | Cannot approve own adjustment |

---

## 📁 Files

| File | Purpose |
|------|---------|
| `Models/Correction.php` | Correction model |
| `Models/InventoryAdjustment.php` | Adjustment model |
| `Services/CorrectionService.php` | Invoice/Collection corrections |
| `Services/InventoryAdjustmentService.php` | Inventory adjustments |
| `migrations/2025_12_13_225700_*` | Database migrations |

---

## 🔗 Related Rules

| Rule | Description |
|------|-------------|
| BR-INV-003 | Invoice cancellation (not deletion) |
| BR-COL-006 | Collection cancellation |
| BR-SHP-007 | Settled shipment protection |
