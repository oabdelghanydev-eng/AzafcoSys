# ADR-001: Return Cancellation Requires Service-Layer Orchestration

**Status**: Implemented  
**Date**: 2025-12-27  
**Severity**: SEV-1 (Ledger Corruption Risk)

---

## Context

When a return is cancelled, the system must reverse **two ledger effects**:

1. **Inventory**: `ShipmentItem.sold_cartons` must be incremented (stock returned to "sold" state)
2. **Customer Balance**: `Customer.balance` must be increased (customer owes more again)

These operations are **non-negotiable invariants**. If either fails to execute:
- Inventory becomes permanently corrupted (sold stock vanishes)
- Customer balances diverge from reality (financial discrepancy)

---

## Problem

A critical bug was discovered where return cancellation logic existed in **two places**:

| Location | Operation | Units Used |
|----------|-----------|------------|
| `ReturnService::cancelReturn()` | Increment `sold_cartons` | `item->cartons` ✓ |
| `ReturnObserver::handleCancellation()` | Increment `sold_cartons` | `item->quantity` ✗ |

**Failure Mode**: `quantity` is weight in kilograms, not cartons. This caused:
- Double-execution of inventory reversal
- Wrong values applied (weight treated as carton count)
- Silent data corruption

Additionally, any code calling `$return->update(['status' => 'cancelled'])` directly would bypass ledger reversal entirely.

---

## Decision

**All return cancellation MUST go through `ReturnService::cancelReturn()`.**

No other code path may transition a Return from `active` to `cancelled` status.

---

## Enforcement

### 1. Observer Logic Removed

`ReturnObserver::handleCancellation()` was **deleted**. The Observer now only handles audit logging.

```php
// ReturnObserver.php
public function updated(ReturnModel $return): void
{
    // NOTE: Cancellation logic is handled EXCLUSIVELY by ReturnService::cancelReturn()
    // DO NOT add cancellation logic here.
    AuditService::logUpdate($return, $return->getOriginal());
}
```

### 2. Model Boot Guard

`ReturnModel` throws an exception if status changes to `cancelled` without authorization:

```php
// ReturnModel.php
public bool $cancelViaService = false;

protected static function boot()
{
    parent::boot();
    
    static::updating(function (ReturnModel $return) {
        if ($return->isDirty('status')) {
            $old = $return->getOriginal('status');
            $new = $return->status;
            
            if ($old === 'active' && $new === 'cancelled') {
                if (!$return->cancelViaService) {
                    throw new BusinessException(
                        'RET_BYPASS',
                        'Return cancellation must use ReturnService::cancelReturn()'
                    );
                }
            }
        }
    });
}
```

### 3. Service Sets Authorization Flag

`ReturnService::cancelReturn()` sets the flag before updating:

```php
$return->cancelViaService = true;
$return->update([
    'status' => 'cancelled',
    'cancelled_by' => auth()->id(),
    'cancelled_at' => now(),
]);
```

---

## What Breaks If Removed

| Guard Removed | Consequence |
|---------------|-------------|
| Boot guard | Direct `->update()` bypasses ledger reversal. Customer balance never restored. Inventory permanently lost. |
| `cancelViaService` flag | Service call throws its own exception, blocking legitimate cancellations. |
| Service-layer logic | Ledger reversal doesn't happen at all. |

---

## Verification

**Tests**: `ReturnCancellationTest`, `Sev1FixVerificationTest`

| Test | Assertion |
|------|-----------|
| `test_cancel_return_updates_customer_balance_exactly_once` | Balance changes by exactly `total_amount`, not double |
| `test_cancel_return_updates_inventory_exactly_once` | `sold_cartons` changes by exactly `cartons`, not `quantity` |
| `test_direct_return_cancellation_throws_exception` | Bypass attempt throws `RET_BYPASS` |
| `test_return_service_cancellation_works_with_guard` | Service path succeeds |

---

## Files

| File | Role |
|------|------|
| [ReturnService.php](file:///d:/System/backend/app/Services/ReturnService.php) | Authoritative cancellation logic |
| [ReturnModel.php](file:///d:/System/backend/app/Models/ReturnModel.php) | Boot guard enforcement |
| [ReturnObserver.php](file:///d:/System/backend/app/Observers/ReturnObserver.php) | Audit only (no ledger logic) |
