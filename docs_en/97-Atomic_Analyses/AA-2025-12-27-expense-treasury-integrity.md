# Atomic Analysis — Expenses & Treasury Integrity

## Scope
Analysis of the Expense creation lifecycle, specifically verifying strict linkage between `Expense` records, `Supplier` balance updates, and `Account` (Cash/Bank) deductions.

## Relevant Files
- `d:\System\backend\app\Models\Expense.php`
- `d:\System\backend\app\Observers\ExpenseObserver.php`
- `d:\System\backend\app\Http\Controllers\Api\ExpenseController.php`

## Observed Behavior
1.  **Creation Flow**: `ExpenseController::store` creates an `Expense` record in a transaction.
2.  **Observer Trigger**: `ExpenseObserver::created` fires.
    *   **Supplier Balance**: If type is 'supplier', it decrements `Supplier` balance.
    *   **Locking**: Uses `lockForUpdate()` on the `Account` model to preventing race conditions during concurrent expenses.
    *   **Validation**: explicitly checks `if ($account->balance < $expense->amount)` and throws `TRS_001` if insufficient.
    *   **Treasury Update**: Decrements `Account` balance and creates `CashboxTransaction` or `BankTransaction`.
3.  **Deletion Flow**: `ExpenseObserver::deleted` handling is robust.
    *   Reverses supplier balance.
    *   Refunding the treasury account.
    *   Hard-deletes the transaction record.

## Documented Expectations
- **Financial Integrity**: Expenses must effectively move money out of the system immediately.
- **Race Condition Safety**: Multiple concurrent expenses should not drive the cashbox negative beyond zero (if enforced).

## Findings

### 1. Robust Atomic Design
- **Type**: Positive Finding
- **Severity**: Low (Good)
- **Description**: The Observer employs `lockForUpdate()`, ensuring that two concurrent requests cannot spend the same cash twice. This is a critical financial invariant that is correctly implemented.
- **Evidence**: `ExpenseObserver.php` (lines 33).

### 2. Transaction Hard-Deletion
- **Type**: Risk
- **Severity**: Low
- **Description**: The `deleted` observer performs a hard delete on the `CashboxTransaction` (`$transaction->delete()`). In strict accounting systems, this is often discouraged (preferring a "Reversal" transaction) to preserve the audit trail of the error.
- **Evidence**: `ExpenseObserver.php` (lines 95-97).

### 3. Missing `updated` Observer
- **Type**: Missing Safeguard
- **Severity**: Medium
- **Description**: There is no `updated` method in `ExpenseObserver`. If an admin edits an expense amount from $100 to $200 via the API (which calls `$expense->update()`), the treasury balance **will not change**, leading to a permanent corruption of the Cashbox balance.
- **Evidence**: `ExpenseObserver.php` (Only `created` and `deleted` exist).

## Accounting Impact
**Partial Integrity**.
Creation and Deletion are safe and robust. **Editing is dangerous** and unsafe currently, as it desynchronizes the Ledger (Cashbox) from the Source Document (Expense).

## Open Questions
1. Is Expense editing disabled in the Policy? If not, this is a live bug.

## Status
ANALYSIS COMPLETE — NO DECISION MADE
