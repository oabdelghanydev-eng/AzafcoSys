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
| Delete | `invoices.delete` | `InvoiceObserver::deleting` | ❌ Forbidden | Deletion strictly forbidden |
| Cancel | `invoices.cancel` | `InvoicePolicy::cancel` | ✅ Enforced | + Edit Window |

### 2. Collections Module ✅ UPDATED
| Action | Required Permission | Enforcement Layer | Status | Notes |
|--------|---------------------|-------------------|--------|-------|
| View | `collections.view` | `CollectionPolicy::view` | ✅ Enforced | |
| Create | `collections.create` | `CollectionPolicy::create` | ✅ Enforced | |
| Edit | `collections.edit` | `CollectionPolicy::update` | ✅ Enforced | + Edit Window |
| Delete | `collections.delete` | `CollectionObserver::deleting` | ❌ Forbidden | Deletion forbidden |
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

## F) Updates (2025-12-16)

### Architecture Improvements

1. ✅ **Centralized Permission Checking**: Moved `checkPermission()` and `ensureAdmin()` to `ApiResponse` Trait
   - Location: `app/Traits/ApiResponse.php`
   - Used by all Controllers that extend the trait
   - Benefits: DRY principle, single source of truth

2. ✅ **Financial Permissions**: Enforced via `ApiResponse::checkPermission()`
   - `cashbox.view`, `cashbox.deposit`, `cashbox.withdraw`
   - `bank.view`, `bank.deposit`, `bank.withdraw`

3. ✅ **Reports Permissions**: Enforced via `ApiResponse::checkPermission()`
   - `reports.daily`, `reports.settlement`, `reports.customers`, `reports.export_pdf`

4. ✅ **Admin-Only Endpoints**: Enforced via `ApiResponse::ensureAdmin()`
   - `AuditLogController` - View audit logs

### Code Pattern

```php
// ApiResponse.php (Trait)
protected function checkPermission(string $permission): void
{
    if (!auth()->user()->hasPermission($permission)) {
        throw new BusinessException('AUTH_003', '...', 'Permission denied');
    }
}

protected function ensureAdmin(): void
{
    if (!auth()->user()->is_admin) {
        throw new BusinessException('AUTH_004', '...', 'Admin access only');
    }
}

// Usage in Controllers
class ReportController extends Controller
{
    use ApiResponse;

    public function daily(Request $request, string $date): JsonResponse
    {
        $this->checkPermission('reports.daily');
        // ...
    }
}
```

---

## G) Status Summary

✅ **All core permissions are now enforced!**

| Category | Enforcement | Status |
|----------|-------------|--------|
| Invoices | Policies | ✅ |
| Collections | Policies | ✅ |
| Shipments | Policies | ✅ |
| Users | Policies | ✅ |
| Financial | ApiResponse Trait | ✅ |
| Reports | ApiResponse Trait | ✅ |
| Audit Logs | ApiResponse Trait (Admin) | ✅ |

---

*Last Updated: 2025-12-16*
