# ADR-002: Invoice Creation Requires Explicit Balance Update

**Status**: Implemented  
**Date**: 2025-12-27  
**Severity**: SEV-1 (Ledger Corruption Risk)

---

## Context

When an invoice is created, the customer's balance must increase by the invoice total. This is a **fundamental accounting invariant**:

```
Customer.balance = Σ(active invoices) - Σ(collections) - Σ(returns)
```

If an invoice is created without updating customer balance, a "ghost invoice" exists in the system — billable but not reflected in the customer's ledger.

---

## Problem

Invoice creation happens in **multiple code paths**:

| Location | Balance Updated? |
|----------|------------------|
| `InvoiceController::store()` | ✓ Yes (line 124-126) |
| `CorrectionService::approveInvoiceCorrection()` | ✗ **NO** — SEV-1 Bug |
| `SimulateDailyWorkflow` (CLI) | ✓ Yes (manual) |

**Critical Bug Found**: `CorrectionService` line 140 contained a **false comment**:
```php
// Customer balance will be updated by InvoiceObserver
```

This was **incorrect**. `InvoiceObserver::created()` does NOT update customer balance — it only:
1. Sets `balance=0` for wastage invoices
2. Logs to AuditService

**Result**: Correction invoices created ledger corruption. Customer balance never increased.

---

## Decision

**Every code path that creates an Invoice must explicitly update Customer.balance.**

There is no implicit Observer-based balance update. Each creation site is responsible.

---

## Enforcement

### 1. Explicit Balance Update in CorrectionService (Fixed)

```php
// CorrectionService.php — approveInvoiceCorrection()

// CRITICAL: Update customer balance for correction invoice
// SEV-1 FIX (2025-12-27): InvoiceObserver::created() does NOT update balance
if ($correctionInvoice->type !== 'wastage') {
    Customer::where('id', $correctionInvoice->customer_id)
        ->increment('balance', (float) $correctionInvoice->total);
}
```

### 2. Model Boot Guard (Detection Only)

Invoice model logs a warning if created with zero/null total (except wastage):

```php
// Invoice.php boot()
static::created(function (Invoice $invoice) {
    if ($invoice->type === 'wastage') return;
    
    if (is_null($invoice->total) || $invoice->total <= 0) {
        Log::warning('Invoice created with invalid total - possible service bypass', [
            'invoice_id' => $invoice->id,
            'total' => $invoice->total,
        ]);
    }
});
```

This is a **detection mechanism**, not prevention. It catches test data and developer errors.

### 3. Cancellation Logic in Observer

When an invoice is cancelled, `InvoiceObserver::handleCancellation()` correctly decrements balance:

```php
if ($invoice->type !== 'wastage') {
    Customer::where('id', $invoice->customer_id)
        ->decrement('balance', (float) $invoice->total);
}
```

---

## What Breaks If Removed

| Component Removed | Consequence |
|-------------------|-------------|
| CorrectionService balance update | Correction invoices don't affect customer balance. Ledger diverges. |
| Boot guard warning | Silent failures in development. Harder to catch bypasses. |
| Cancellation balance decrement | Cancelled invoices leave balance inflated. Customer overbilled. |

---

## Rejected Alternative

**Moving balance update to InvoiceObserver::created()** was rejected because:
1. Observer fires BEFORE total is set in Controller pattern (Invoice created, then updated with totals)
2. Would require two-phase logic or `saved` event complexity
3. Explicit updates are clearer and easier to audit

---

## Verification

**Tests**: `Sev1FixVerificationTest` (skipped pending corrections table migration)

Manual verification performed on CorrectionService fix.

---

## Files

| File | Role |
|------|------|
| [CorrectionService.php](file:///d:/System/backend/app/Services/CorrectionService.php) | Balance update on correction approval |
| [InvoiceController.php](file:///d:/System/backend/app/Http/Controllers/Api/InvoiceController.php) | Primary invoice creation with balance |
| [Invoice.php](file:///d:/System/backend/app/Models/Invoice.php) | Boot guard (detection) |
| [InvoiceObserver.php](file:///d:/System/backend/app/Observers/InvoiceObserver.php) | Cancellation balance reversal |
