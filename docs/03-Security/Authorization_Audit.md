# Authorization Coverage Review

**Date:** 2025-12-14 (Updated)
**Reviewer:** Senior Security Architect
**Scope:** Permissions, Policies, Middleware, and Business Rules enforcement.

---

## A) Permission Model Summary

- **Storage:** `permissions` column in `users` table (JSON Array).
- **Format:** String codes (e.g., `"invoices.create"`, `"shipments.view"`).
- **Enforcement Layer:**
  - **Middleware:** `auth:sanctum` (Global).
  - **Policies:** Laravel Policies checking `user->hasPermission()`.
  - **Admin Bypass:** `is_admin = true` bypasses all permission checks.

---

## B) Authorization Coverage Matrix

### 1. Invoices Module ✅ UPDATED
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| View Any/One | `invoices.view` | `InvoicePolicy::view` | ✅ Enforced | |
| Create | `invoices.create` | `InvoicePolicy::create` | ✅ Enforced | |
| Edit | `invoices.edit` | `InvoicePolicy::update` | ✅ Enforced | + Edit Window |
| Delete | `invoices.delete` | `InvoiceObserver::deleting` | ❌ Forbidden | الحذف ممنوع نهائياً |
| Cancel | `invoices.cancel` | `InvoicePolicy::cancel` | ✅ Enforced | + Edit Window |

### 2. Collections Module ✅ UPDATED
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| View | `collections.view` | `CollectionPolicy::view` | ✅ Enforced | |
| Create | `collections.create` | `CollectionPolicy::create` | ✅ Enforced | |
| Edit | `collections.edit` | `CollectionPolicy::update` | ✅ Enforced | + Edit Window |
| Delete | `collections.delete` | `CollectionObserver::deleting` | ❌ Forbidden | الحذف ممنوع |
| Cancel | `collections.cancel` | `CollectionPolicy::cancel` | ✅ Enforced | + Edit Window |

### 3. Users Module ✅ NEW
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| View | `users.view` | `UserPolicy::view` | ✅ Enforced | |
| Create | `users.create` | `UserPolicy::create` | ✅ Enforced | |
| Edit | `users.edit` | `UserPolicy::update` | ✅ Enforced | |
| Delete | `users.delete` | `UserPolicy::delete` | ✅ Enforced | + Self-delete blocked |
| Unlock | `users.unlock` | `UserPolicy::unlock` | ✅ Enforced | |
| Update Permissions | `users.edit` | `UserController` | ✅ Enforced | + Own-edit blocked |

### 4. Shipments Module ✅ UPDATED
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| View | `shipments.view` | `ShipmentPolicy::view` | ✅ Enforced | |
| Create | `shipments.create` | `ShipmentPolicy::create` | ✅ Enforced | |
| Edit | `shipments.edit` | `ShipmentPolicy::update` | ✅ Enforced | + Settled check |
| Delete | `shipments.delete` | `ShipmentPolicy::delete` | ✅ Enforced | + Settled check |
| Close | `shipments.close` | `ShipmentPolicy::close` | ✅ Enforced | |
| Settle | `shipments.close` | `ShipmentPolicy::settle` | ✅ Enforced | |
| Unsettle | `shipments.close` | `ShipmentPolicy::unsettle` | ✅ Enforced | |

### 5. Corrections Module ✅ NEW
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| Create | (Auth only) | `CorrectionService` | ✅ Auth Required | |
| Approve | `corrections.approve` | `Correction::canBeApprovedBy` | ✅ Enforced | Maker-Checker |

### 6. Inventory Adjustments ✅ NEW
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| Create | (Auth only) | `InventoryAdjustmentService` | ✅ Auth Required | |
| Approve | `inventory.adjust` | `InventoryAdjustment::canBeApprovedBy` | ✅ Enforced | Maker-Checker |

---

## C) Policies Using hasPermission() ✅

| Policy | hasPermission | Registered | Status |
|--------|---------------|------------|--------|
| InvoicePolicy | ✅ | ✅ | **Fully Enforced** |
| CollectionPolicy | ✅ | ✅ | **Fully Enforced** |
| UserPolicy | ✅ | ✅ | **Fully Enforced** |
| ShipmentPolicy | ✅ | ✅ | **Fully Enforced** |

---

## D) Summary

| Category | Count |
|----------|-------|
| **Total Permissions Defined** | 50 |
| **Fully Enforced** | ~45 |
| **Needs Permission Check** | ~5 (Financial) |
| **Forbidden (by design)** | 4 (Delete Invoice/Collection) |

---

## E) Recent Updates (2025-12-14)

1. ✅ Created `UserController` with full CRUD
2. ✅ Created `UserPolicy` with `hasPermission()` checks
3. ✅ Updated `InvoicePolicy` to use `hasPermission()`
4. ✅ Updated `CollectionPolicy` to use `hasPermission()`
5. ✅ Created `ShipmentPolicy` with `hasPermission()` checks
6. ✅ Added `corrections.approve` permission
7. ✅ Added `inventory.adjust` for Maker-Checker workflow

---

## F) Remaining Work

1. Add permissions for financial operations (Cashbox, Bank)
2. Add permissions for Reports module
