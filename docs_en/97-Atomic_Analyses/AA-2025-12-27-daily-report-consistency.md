# Atomic Analysis — Daily Report Consistency

## Scope
Analysis of the `DailyReport` structure and the `DailyReportService`, specifically verifying whether reports represent immutable snapshots of a day's activity or dynamic aggregations liable to historical drift.

## Relevant Files
- `d:\System\backend\app\Models\DailyReport.php`
- `d:\System\backend\app\Services\DailyReportService.php`

## Observed Behavior
1.  **Opening**: Creates a record with `cashbox_opening` copied from yesterday's `closing`.
2.  **Closing (`closeDay`)**:
    *   Calculates totals dynamically by summing `Invoice`, `Collection`, and `Expense` records for that specific date.
    *   Updates the `DailyReport` record with these sums.
    *   Marks status as `closed`.
3.  **Reopening**: Allowed (`reopenDay`).
    *   Sets status back to `open`.
    *   Allows adding/editing transactions for that date.
4.  **No Snapshotting**: The report stores the *results* of the calculation (totals), but does not freeze the underlying transactions.

## Documented Expectations
- **Fiscal Day**: A closed day should ideally be immutable to preserve the audit trail.
- **Cash Flow Continuity**: Closing balance of Day N must match Opening balance of Day N+1.

## Findings

### 1. Reports are Aggregations, Not Snapshots
- **Type**: Architectural Fact
- **Description**: The `DailyReport` is a "Summary Cache" of the transactions. If a developer manually inserts an invoice into a past date using `tinker` or a seeder, the *closed* daily report for that day will be **incorrect/stale** until it is reopened and re-closed.
- **Evidence**: `DailyReportService.php` (lines 153-192).

### 2. Historical drift enabled by Reopening
- **Type**: Risk
- **Severity**: Medium
- **Description**: Since reopening is permitted (`DAY_003`), an admin can modify history. While useful for corrections, it breaks the guarantee that "Closed means Final".
- **Evidence**: `DailyReportService.php` (lines 261-282).

### 3. "Last Closing Balance" Lookback is Fragile
- **Type**: Fragility
- **Severity**: Medium
- **Description**: When opening a new day, the system looks for the *last* closed report (`orderByDesc('date')->first()`). If there are gaps (e.g., missed days), it assumes the cash stayed in the box. This is logically correct but relies on the assumption that no "off-book" movements happened during the gap.
- **Evidence**: `DailyReportService.php` (lines 308-323).

## Accounting Impact
**Consistent but Mutable**.
The system prioritizes *correctness of current value* (via dynamic calculation) over *immutability of history*. This is acceptable for operational accounting but requires strict audit logs on the `Reopen` action (which exist: `logUserEvent`).

## Open Questions
1. Does the system strictly prevent editing transactions that belong to a `closed` report *without* reopening it first? (Need to check Policies).

## Status
ANALYSIS COMPLETE — NO DECISION MADE
