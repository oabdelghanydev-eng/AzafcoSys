# Atomic Analysis — Collections & Customer Balance Integrity

## Scope
Analysis of the Collection creation lifecycle, ensuring strict linkage between `Collection` records, `Customer` balance updates, and `Account` (Cash/Bank) entries.

## Relevant Files
- `d:\System\backend\app\Models\Collection.php`
- `d:\System\backend\app\Observers\CollectionObserver.php`
- `d:\System\backend\app\Http\Controllers\Api\CollectionController.php`

## Observed Behavior
1.  **Creation Flow**: `CollectionController::store` creates the `Collection` record inside a transaction.
2.  **Observer Trigger**: `CollectionObserver::created` fires.
    *   **Customer Balance**: Decrements `Customer` balance immediately (`$customer->decrement('balance')`).
    *   **Treasury Update**: Increments `Account` (Cash/Bank) balance and creates a `Transaction` record.
    *   **Distribution**: Calls `CollectionDistributorService` for auto-allocation.
3.  **Cancellation Flow**: `CollectionObserver::updated` handles status changes.
    *   Reverses allocations.
    *   Increments `Customer` balance back.

## Documented Expectations
- **BR_Catalogue.md (BR-COL-001)**: "Collection creation must immediately reduce customer debit balance."
- **Consistency**: Implies atomic updates for all related ledgers.

## Findings

### 1. Robust Observer Implementation (Contrast to Invoices)
- **Type**: Positive Finding
- **Severity**: Low (Good)
- **Description**: Unlike `InvoiceObserver`, the `CollectionObserver` **does** handle balance updates. This means a Collection created via Seeder, CLI, or API will *always* update the customer balance.
- **Evidence**: `CollectionObserver.php` (lines 29-31).

### 2. Hidden Treasury Side-Effects
- **Type**: Undocumented Assumption
- **Severity**: Medium
- **Description**: The Observer handles *Financial Accounting* (Cash/Bank updates) implicitly. While convenient, it means testing `Collection` logic requires mocking `Account` models, otherwise tests will unintentionally modify real or test-database treasury balances. It also hampers "Data Import" scenarios where we might want to import legacy collections *without* affecting the current Cashbox balance.
- **Evidence**: `CollectionObserver.php` (lines 33-66).

### 3. Hardcoded "Cashbox/Bank" Lookup
- **Type**: Fragility
- **Severity**: Low
- **Description**: The Observer assumes there is exactly one active account of type `cashbox` or `bank`.
- **Evidence**: `CollectionObserver.php` (lines 35-37: `first()`).

## Accounting Impact
**Consistent**.
The Collection module enforces accounting invariants much better than the Invoice module. "Ghost Collections" (collections without balance impact) are impossible under the current architecture.

## Open Questions
1. How do we handle "Legacy Data Import" if every collection creation forcibly updates the live Cashbox balance?

## Status
ANALYSIS COMPLETE — NO DECISION MADE
