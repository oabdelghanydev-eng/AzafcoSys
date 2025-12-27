# Atomic Analysis — Inventory Atomicity & FIFO Logic

## Scope
Analysis of the Inventory Allocation logic, specifically the `FifoAllocatorService` and `ShipmentItem` model, ensuring that allocating stock (Invoice creation) and releasing stock (Cancellation) are atomic and consistent.

## Relevant Files
- `d:\System\backend\app\Services\FifoAllocatorService.php`
- `d:\System\backend\app\Models\ShipmentItem.php`
- `d:\System\backend\app\Models\InvoiceItem.php`

## Observed Behavior
1.  **Allocation Flow**: `FifoAllocatorService::allocateAndCreate` executes inside a DB Transaction.
    *   Locks `ShipmentItem` rows (`pessimistic_write`).
    *   Iterates strictly by `fifo_sequence`.
    *   Updates `ShipmentItem` (`increment('sold_cartons')`) and creates `InvoiceItem` atomically.
    *   This logic is **Service-Bound**, meaning creating an `InvoiceItem` manually (via Seeder/Test) *fails to update inventory* unless the Service is explicitly used.
2.  **Cancellation Flow**: `FifoAllocatorService::reverseAllocation` handles reversal.
    *   Finds `InvoiceItem`.
    *   Decrements `ShipmentItem->sold_cartons`.
3.  **State Management**: `ShipmentItem` uses computed columns for `remaining_cartons` which relies on `sold_cartons`.

## Documented Expectations
- **FIFO Compliance**: Strict First-In-First-Out allocation.
- **Inventory Integrity**: Sales must always reduce inventory; Cancellations must restore it.

## Findings

### 1. Hard Dependency on Service Layer
- **Type**: Missing Safeguard
- **Severity**: High
- **Description**: The inventory update logic lives entirely in `FifoAllocatorService`. There is no `InvoiceItemObserver` to enforce that creating an invoice item *must* deduct from inventory.
- **Impact**: Any direct creation of `InvoiceItem` (e.g. in Seeders, Imports, or Tests) results in "Ghost Allocations" where the Invoice says "Sold" but the Shipment says "Available".
- **Evidence**: `FifoAllocatorService.php` (lines 89-130).

### 2. Raw SQL Vulnerability in Availability Check
- **Type**: Fragility
- **Severity**: Medium
- **Description**: `getAvailableStock` uses a raw SQL fragment `(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) > 0`. If the column names change, this hardcoded SQL string will fail silently or loudly, unlike Eloquent accessors.
- **Evidence**: `FifoAllocatorService.php` (lines 153).

### 3. Missing Atomic safeguards for Manual Adjustments
- **Type**: Missing
- **Severity**: Low
- **Description**: There is no audit trail or observer for direct modifications to `ShipmentItem->sold_cartons`. If an admin manually edits this field (DB access), history is lost.

## Accounting Impact
**Conditional Integrity**.
Integrity exists **only if** the `FifoAllocatorService` is the unique entry point. The architecture does not enforce inventory integrity at the Database or Model level.

## Open Questions
1.  Are there any bulk-import tools that bypass `FifoAllocatorService`? (If yes, Inventory is corrupt).

## Status
ANALYSIS COMPLETE — NO DECISION MADE
