# Decision Framing — Post Audit

## Decision 1: Resolve Double-Crediting in Returns
**Question:** Where should the "Ledger Reversal" logic live for Return Cancellations?

- **Option A: Service-Only (Recommended)**
    - Remove the logic from `ReturnObserver`.
    - Rely entirely on `ReturnService` to handle the transaction.
    - **Pros:** Explicit control, easier to test, matches current "Service-driven" patterns in Invoices.
    - **Cons:** Bypassing the Service (e.g. `Return::update()`) will not reverse the ledger.

- **Option B: Observer-Only**
    - Remove the logic from `ReturnService`.
    - Rely on `ReturnObserver` listening to `status` changes.
    - **Pros:** Safety by default (updating the model updates the ledger).
    - **Cons:** Harder to manage race conditions or complex validation during the cancellation process.

- **Risk of Deferral:** **CRITICAL**. The system currently charges the customer *twice* the reversal amount when a return is cancelled. This is active financial corruption.
- **Modules Affected:** Returns, Collections (Ledger), Inventory.

---

## Decision 2: Enforce Invoice Ledger Integrity
**Question:** How should we guarantee that creating an Invoice updates the Customer Balance?

- **Option A: Move to Observer (Atomic Model)**
    - Migrate balance update logic from `InvoiceController` to `InvoiceObserver`.
    - **Pros:** "Ghost Invoices" become impossible. Seeders/Factories work automatically.
    - **Cons:** Requires handling the "Zero Total" issue (Observer fires before items are added).

- **Option B: Enforce Service Layer (Strict Gateway)**
    - Deprecate `Invoice::create`. Requires all creation to go through `InvoiceCreationService`.
    - **Pros:** Keeps logic explicit.
    - **Cons:** Hard to enforce. Developers can still call `Invoice::create` and corrupt the ledger.

- **Risk of Deferral:** **HIGH**. External integrations or new developers will inevitably create invoices that do not exist in the General Ledger.
- **Modules Affected:** Invoices, Customers, Reporting.

---

## Decision 3: Enforce Inventory Allocations
**Question:** Should Inventory Deduction be a database invariant or a business service rule?

- **Option A: Database/Observer Invariant**
    - Use Observers on `InvoiceItem` to automatically deduct `ShipmentItem` stock.
    - **Pros:** Impossible to sell stock "off the books".
    - **Cons:** Performance impact. Complex circular dependency if validations fail.

- **Option B: Service-Bound (Current)**
    - Rely on `FifoAllocatorService`.
    - **Pros:** Flexible. Performance optimized.
    - **Cons:** "Ghost Allocations" are possible if Model is accessed directly.

- **Risk of Deferral:** **HIGH**. Import tools or CLI scripts bypassing the Service will de-sync physical stock from system stock.
- **Modules Affected:** Inventory, Shipments, Invoices.

---

## Decision 4: Daily Report Immutability
**Question:** Should we strictly lock down "Closed" fiscal days?

- **Option A: Strict Immutability**
    - Prevent `update/delete` on any transaction dated to a `closed` DailyReport.
    - **Pros:** Absolute audit safety.
    - **Cons:** Operational friction (requires "Reopening" the day explicitly to fix typos).

- **Option B: Audit-Only (Current)**
    - Allow changes, rely on `AuditLog` to catch bad actors.
    - **Pros:** High flexibility.
    - **Cons:** Historical reports become unreliable (drift).

- **Risk of Deferral:** Medium. Reports may slowly diverge from reality as users retroactively edit old transactions.
- **Modules Affected:** Reporting, All Financial Modules.

---

**Implementation is blocked until these decisions are approved.**
