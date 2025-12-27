# Atomic Analysis — Correction Capabilities (Edit Window)

## Scope
Analysis of the `Correction` module and `CorrectionService`, specifically the "Maker-Checker" workflow and how it impacts the General Ledger. The system claims to use "Soft Corrections" (Adjustments) rather than modifying original historical records.

## Relevant Files
- `d:\System\backend\app\Models\Correction.php`
- `d:\System\backend\app\Services\CorrectionService.php`

## Observed Behavior
1.  **Immutability Strategy**: The system relies on "Adjustment Entries" rather than editing original records.
    *   `createInvoiceCorrection`: Creates a `Correction` record (Pending).
    *   `approveInvoiceCorrection`: Creates a *new* `Invoice` record (Type: `adjustment`) linked to the original.
2.  **Maker-Checker Enforcement**:
    *   Explicitly checks `$user->id !== $this->created_by` (Line 100 in `Correction.php`).
    *   Checks `corrections.approve` permission.
3.  **Inheritance of Behavior**:
    *   Approved Invoice Corrections create a new `Invoice`. This means they **inherit the same risks** as the Invoice Module (e.g. Controller vs Model ledger fragmentation).
    *   Approved Collection Corrections create a new `Collection`. This means they **inherit the safety** of the Observer-driven Collection module.

## Documented Expectations
- **Audit Trail**: Every change to history must be a new transaction.
- **Segregation of Duties**: The person requesting the change cannot match the person approving it.

## Findings

### 1. Robust Audit & Workflow
- **Type**: Positive Finding
- **Severity**: Low (Good)
- **Description**: The Maker-Checker implementation is strict and robust. The use of "Adjustment Invoices" instead of updating the original `total` column is an Architectural Best Practice for accounting systems.
- **Evidence**: `CorrectionService.php` (Line 117-131).

### 2. Negative Collections Handling
- **Type**: Risk
- **Severity**: Medium
- **Description**: `approveCollectionCorrection` allows creating a `Collection` with a **negative amount** (Line 219 `adjustment_value`).
    *   While technically correct for a refund/reversal, does the `CollectionObserver` handle negative values correctly?
    *   `CollectionObserver`: `$customer->decrement('balance', $amount)`. If amount is negative, `decrement(-100)` becomes `increment(100)`. **Math works**.
    *   `CashboxTransaction`: `create(... 'amount' => $amount)`. If amount is negative, the transaction log will show a negative "In". This might break reporting if reports expect "In" to always be positive and "Out" to be separate.

### 3. Missing Inventory Impact on Invoice Correction
- **Type**: Gap
- **Severity**: High
- **Description**: `createInvoiceCorrection` creates an Adjustment Invoice with a dollar value (`subtotal`) but **zero items** (Lines 117-131).
    *   If a correction is needed because "Prices were wrong", this works.
    *   If a correction is needed because "Quantities were wrong" (e.g. 5 boxes instead of 10), this Adjustment Invoice **does not adjust inventory**.
    *   To fix inventory, one must use `Returns`.
    *   **Ambiguity**: Users might try to fix "Wrong Quantity" via Correction (financial fix) and forget the Inventory fix, leaving Stock Count incorrect.

## Accounting Impact
**High Integrity (Financial), Low Integrity (Inventory).**
The module protects the Ledger well but desynchronizes Inventory if used for quantity corrections.

## Open Questions
1.  Does the Frontend force users to use "Returns" for quantity errors, or can they "Correct" an invoice amount down?

## Status
ANALYSIS COMPLETE — NO DECISION MADE
