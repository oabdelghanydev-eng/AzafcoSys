# Backend Documentation Compliance – Findings Log

This file tracks deviations, partial implementations, and future improvements
found during backend documentation compliance reviews.

**Last Updated:** 2025-12-13

---

## Sales Module

### ✅ Fixed (2025-12-13)
- **BR-INV-003 – No deletion allowed (use cancellation)**
  - Status: ✅ Fixed
  - Implementation: `InvoiceObserver::deleting()` throws exception
  - Policy: `InvoicePolicy::delete()` returns false

- **Discount validation (discount <= subtotal)**
  - Status: ✅ Fixed
  - Implementation: `StoreInvoiceRequest::withValidator()` calculates subtotal and validates
  - Also validates: total > 0 (unless wastage)

### ⚠️ Partial Implementations
- **BR-INV-005 – Prevent reducing invoice total below paid amount**
  - Status: ⚠️ Partial
  - Current behavior: Protected at observer level, no explicit validation rule
  - Risk level: Low
  - Decision: Accept as MVP, improve later

### ❌ Enhancement Candidates
- **Price anomaly logic** – Not specified, Enhancement candidate (Phase 2)

---

## Collections Module

### ✅ Fixed (2025-12-13)
- **BR-COL-005 – lockForUpdate() for race condition protection**
  - Status: ✅ Fixed
  - Implementation: Added `lockForUpdate()` in `CollectionDistributorService::distributeAuto()`

- **BR-COL-007 – No deletion allowed (use cancellation)**
  - Status: ✅ Fixed
  - Implementation: `CollectionObserver::deleting()` throws exception
  - Policy: `CollectionPolicy::delete()` returns false (NEW FILE)

- **BR-COL-006 – Cancellation logic**
  - Status: ✅ Fixed
  - Implementation: `CollectionObserver::updated()` handles status → cancelled

- **LIFO (newest_first) distribution**
  - Status: ✅ Fixed
  - Implementation: `distributeAuto()` now checks `distribution_method` and sorts accordingly

---

## Inventory Module

### ✅ Fixed (2025-12-13)
- **FIFO ordering by shipment date**
  - Status: ✅ Fixed
  - Previous: Used `created_at`
  - Now: Uses `shipments.date` via JOIN
  - Files fixed: `FifoAllocatorService.php` (allocate + getFifoBreakdown)

---

## Shipments Module

### ✔️ Full Compliance
- **BR-SHP-001**: Shipment status transitions (open → closed → settled) – ✅ Match
- **BR-SHP-002**: Auto-close when remaining = 0 – ✅ Match (`ShipmentItemObserver`)
- **BR-SHP-003**: Settlement with carryover – ✅ Match (`ShipmentService::settle`)
- **BR-SHP-004**: Unsettle with reversal – ✅ Match (`ShipmentService::unsettle`)
- **BR-SHP-005**: Unsettle protection (can't if sold) – ✅ Match
- **BR-SHP-006**: Prevent delete with invoices – ✅ Match (`ShipmentObserver::deleting`)

### ✅ Fixed (2025-12-13)
- **BR-SHP-007 – Prevent modifying settled shipment (all fields)**
  - Status: ✅ Fixed
  - Implementation: `ShipmentObserver::updating()` checks all dirty fields
  - Only status (for unsettle) and updated_at are allowed

---

## Summary

| Module | Fixed | Partial | Enhancement |
|--------|-------|---------|-------------|
| Sales | 2 | 1 | 1 |
| Collections | 4 | 0 | 0 |
| Inventory | 1 | 0 | 0 |
| Shipments | 7 | 0 | 0 |
| **Total** | **14** | **1** | **1** |

---

## Files Modified (2025-12-13)

1. `app/Services/FifoAllocatorService.php` – FIFO ordering fix
2. `app/Services/CollectionDistributorService.php` – lockForUpdate() + newest_first
3. `app/Observers/CollectionObserver.php` – deleting + cancellation logic
4. `app/Policies/CollectionPolicy.php` – NEW FILE
5. `app/Http/Requests/Api/StoreInvoiceRequest.php` – discount validation
6. `app/Observers/ShipmentObserver.php` – settled shipment protection
