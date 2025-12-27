# Atomic Analysis — Invoice Creation & Balance Updates

## Scope
Analysis of the Invoice Creation lifecycle, specifically focusing on the synchronization between Invoice persistence and Customer Balance updates.

## Relevant Files
- `d:\System\backend\app\Http\Controllers\Api\InvoiceController.php`
- `d:\System\backend\app\Observers\InvoiceObserver.php`
- `d:\System\docs_en\01-Business_Logic\BR_Catalogue.md`
- `d:\System\backend\database\seeders\DemoSeeder.php`

## Observed Behavior
1.  **Invoice Creation**: The `InvoiceController::store` method creates an `Invoice` model first.
2.  **Observer Trigger**: The `InvoiceObserver::created` event fires immediately upon creation.
    *   It checks `if ($invoice->type === 'wastage')` to set balance to 0.
    *   It **does not** update the Customer's balance.
3.  **FIFO & Totals**: The Controller then executes FIFO allocation and `InvoiceItem` creation, determining the final `subtotal` and `total`.
4.  **Balance Update**: The Controller manually triggers the Customer balance update: `Customer::where('id')->increment('balance', $total)`.
5.  **Seeder Behavior**: Seeders (`DemoSeeder.php`) replicate this manual pattern, explicitly incrementing customer balance after creating invoices.

## Documented Expectations
- **BR_Catalogue.md (BR-INV-001)**: Implies that the system maintains balance integrity automatically.
- **General Architecture**: The documentation suggests an "Observer-driven" approach for accounting invariants to ensure a Single Source of Truth.

## Findings

### 1. Balance Logic Fragmentation
- **Type**: Contradiction
- **Severity**: High
- **Description**: The responsibility for updating the General Ledger (Customer Balance) is split. The *definition* of the transaction is in the Model, but the *execution* of the ledger impact is in the Controller (and Seeders).
- **Evidence**: 
    - `InvoiceController.php` (lines 124-126)
    - `InvoiceObserver.php` (lines 24-26: explicitly notes limitation)

### 2. Lifecycle Timing Mismatch
- **Type**: Undocumented Assumption
- **Severity**: Medium
- **Description**: The system implicitly assumes that an Invoice's "Created" event is insufficient for accounting purposes because the Total is calculated *after* item insertion.
- **Evidence**: `InvoiceController.php` logic flow (Create Header -> Add Items -> Update Header -> Update Customer).

### 3. Risk of "Ghost Invoices"
- **Type**: Missing Safeguard
- **Severity**: Critical
- **Description**: There is no database trigger or model-level guard to prevent an Invoice from existing without a corresponding Customer Balance update. Any direct usage of `Invoice::create` (e.g. in a new import script) will inevitably corrupt the ledger.
- **Evidence**: `InvoiceFactory.php` (creates invoice but does not update customer balance).

## Accounting Impact
**YES**. 
This structure permits a state where the Sub-ledger (Sum of Invoices) diverges from the General Ledger (Customer Balance). While the current API path is patched, the underlying architecture violates the principle of "Double-Entry Integrity at the Atomic Level".

## Open Questions
1.  Why was `InvoiceObserver::updated` not used to listen for changes to the `total` column?
2.  Can the FIFO logic be encapsulated in a Service that atomically handles both Invoice creation and Balance updates?
3.  Are there currently any background jobs generating invoices that rely solely on `Invoice::create`?

## Status
ANALYSIS COMPLETE — NO DECISION MADE
