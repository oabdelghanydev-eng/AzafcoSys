# ADR-003: Inventory Allocations Protected by Database Constraint

**Status**: Implemented (Migration Pending)  
**Date**: 2025-12-27  
**Severity**: HIGH (Data Integrity)

---

## Context

The inventory system tracks cartons through the FIFO allocation model:

```
Available = cartons + carryover_in_cartons - carryover_out_cartons - sold_cartons
```

The invariant is:
```
sold_cartons <= cartons + carryover_in_cartons - carryover_out_cartons
```

Violation means the system has "sold" more than exists — a physical impossibility that corrupts inventory records.

---

## Problem

`sold_cartons` is modified in multiple places:

| Location | Operation |
|----------|-----------|
| `FifoAllocatorService::allocateAndCreate()` | Increment on sale |
| `FifoAllocatorService::reverseAllocation()` | Decrement on invoice cancel |
| `ReturnService::createReturn()` | Decrement on return |
| `ReturnService::cancelReturn()` | Increment on return cancel |
| `SimulateDailyWorkflow` (CLI) | Manual increment |

**Risk**: Direct SQL or model manipulation could violate the invariant.

---

## Decision

**The invariant is enforced at database level via CHECK constraint.**

Application-level code uses `FifoAllocatorService` as the authorized path, but the database provides ultimate protection.

---

## Enforcement

### 1. Database CHECK Constraint

```sql
ALTER TABLE shipment_items 
ADD CONSTRAINT chk_sold_cartons_not_exceed_available 
CHECK (sold_cartons <= (cartons + carryover_in_cartons - carryover_out_cartons))
```

**Migration**: `2025_12_27_000001_add_check_constraint_to_shipment_items.php`

**Note**: Requires MySQL 8.0.16+ for enforcement. Earlier versions silently ignore CHECK constraints.

### 2. Service-Layer Centralization

`FifoAllocatorService` is the authoritative service for:
- Allocation (choosing which shipment to sell from)
- Reversal (returning stock on cancellation)

Uses `lockForUpdate()` for concurrency protection.

### 3. Negative Check in ReturnService (Recommended)

Returns decrement `sold_cartons`. Should validate:

```php
if ($targetShipmentItem->sold_cartons < $cartons) {
    throw new BusinessException('RET_004', 'Cannot return more than sold');
}
```

---

## What Breaks If Removed

| Component Removed | Consequence |
|-------------------|-------------|
| CHECK constraint | Over-allocation possible via raw SQL. Inventory becomes negative. |
| FifoAllocatorService | No FIFO ordering. Incorrect cost accounting. |
| Lock mechanism | Race conditions cause double-allocation of same cartons. |

---

## Deployment Note

**Before running migration**, verify MySQL version:
```sql
SELECT VERSION();
-- Must be 8.0.16 or higher for CHECK enforcement
```

---

## Files

| File | Role |
|------|------|
| [Migration](file:///d:/System/backend/database/migrations/2025_12_27_000001_add_check_constraint_to_shipment_items.php) | CHECK constraint |
| [FifoAllocatorService.php](file:///d:/System/backend/app/Services/FifoAllocatorService.php) | Allocation logic with locks |
| [ReturnService.php](file:///d:/System/backend/app/Services/ReturnService.php) | Return inventory logic |
