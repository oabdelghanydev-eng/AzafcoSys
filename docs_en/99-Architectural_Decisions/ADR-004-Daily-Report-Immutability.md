# ADR-004: Daily Report Immutability via Trait Enforcement

**Status**: Implemented  
**Date**: 2025-12-27  
**Severity**: HIGH (Audit Compliance)

---

## Context

A DailyReport represents a closed fiscal day. Once closed:
- All financial calculations are finalized
- Reports have been generated or archived
- Numbers should no longer change

Allowing modifications to transactions on closed days:
- Invalidates historical reports
- Creates audit discrepancies
- Enables retroactive fraud

---

## Problem

Previously, there was no enforcement. Any transaction (Invoice, Collection, Expense, Return) could be modified regardless of whether its date fell on a closed daily report.

---

## Decision

**Transactions on closed days are immutable. Updates and deletes are blocked at the model layer.**

A trait-based enforcement ensures consistent behavior across all financial models.

---

## Enforcement

### Trait: `ChecksClosedDailyReport`

Applied to: `Invoice`, `Collection`, `Expense`, `ReturnModel`

```php
trait ChecksClosedDailyReport
{
    public static function bootChecksClosedDailyReport(): void
    {
        static::updating(function ($model) {
            static::checkDayNotClosed($model, 'update');
        });

        static::deleting(function ($model) {
            static::checkDayNotClosed($model, 'delete');
        });
    }

    protected static function checkDayNotClosed($model, string $action): void
    {
        $date = $model->date;
        
        $closedReport = DailyReport::where('date', $date)
            ->where('status', 'closed')
            ->exists();

        if ($closedReport) {
            throw new BusinessException(
                'DAY_001',
                "Cannot {$action} transaction on a closed day."
            );
        }
    }
}
```

### Escape Hatch

To modify a transaction on a closed day:
1. **Reopen the day** (`DailyReport.status = 'open'`)
2. Make the modification
3. Close the day again

This is logged and auditable. The trait does not have a bypass flag — reopening is the only authorized path.

---

## What Breaks If Removed

| Scenario | Consequence |
|----------|-------------|
| Trait removed from Invoice | Past invoices can be modified. Historical reports become inaccurate. |
| Trait removed from Collection | Collections can be reallocated retroactively. Balance history corrupted. |
| No trait check at all | Audit compliance violation. Potential fraud vector. |

---

## Known Bypass Vectors

| Method | Bypasses Trait? | Mitigation |
|--------|-----------------|------------|
| `Model::update()` | No | ✓ Trait catches |
| `Model::delete()` | No | ✓ Trait catches |
| `saveQuietly()` | **Yes** | Code review policy |
| `DB::table()->update()` | **Yes** | Code review policy |
| `updateQuietly()` | **Yes** | Code review policy |

**Recommendation**: Consider database-level triggers for absolute enforcement (future enhancement).

---

## Verification

**Tests**: `ClosedDayImmutabilityTest`

| Test | Assertion |
|------|-----------|
| `test_cannot_update_invoice_on_closed_day` | Exception thrown |
| `test_can_update_invoice_on_open_day` | Update succeeds |
| `test_cannot_update_collection_on_closed_day` | Exception thrown |
| `test_cannot_update_expense_on_closed_day` | Exception thrown |
| `test_cannot_update_return_on_closed_day` | Exception thrown |
| `test_can_update_transaction_with_no_daily_report` | Update succeeds (no report = no block) |
| `test_can_update_after_day_reopened` | Update succeeds after reopen |

**Result**: 7 tests passed.

---

## Files

| File | Role |
|------|------|
| [ChecksClosedDailyReport.php](file:///d:/System/backend/app/Traits/ChecksClosedDailyReport.php) | Trait implementation |
| [Invoice.php](file:///d:/System/backend/app/Models/Invoice.php) | Uses trait |
| [Collection.php](file:///d:/System/backend/app/Models/Collection.php) | Uses trait |
| [Expense.php](file:///d:/System/backend/app/Models/Expense.php) | Uses trait |
| [ReturnModel.php](file:///d:/System/backend/app/Models/ReturnModel.php) | Uses trait |
