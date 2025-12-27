# NON-NORMATIVE — REQUIRES DECISION
**Snapshot ID:** SNAP-2025-12-27-CONSOLIDATED (V2)
**Title:** Comprehensive System Audit & Architectural Integrity Report
**Date:** 2025-12-27
**Status:** **CRITICAL — IMMEDIATE ARCHITECTURAL ACTION REQUIRED**

---

## Executive Summary
The extended audit (covering Financials, Inventory, Reporting, Returns, and Corrections) confirms that while the system functions for standard use cases, it contains **Critical Accounting Flaws** that prevent safe scaling.

**Highest Priority Risk impacting Business Continuity:**
*   **Returns/Refunds**: Logic duplication between `ReturnService` and `ReturnObserver` is causing **Double Reversal** of ledger entries upon cancellation.

---

## 1. Systemic Risks & Patterns

| Pattern | Modules | Risk Level | Description |
| :--- | :--- | :--- | :--- |
| **Logic Duplication** | Returns | **CRITICAL** | Service handles reversal manualy; Observer handles it automatically. Result: Double Credit. |
| **Logic Fragmentation** | Invoices | **HIGH** | Sub-ledger (Invoice) and General Ledger (Customer Balance) updates are split between Model and Controller. |
| **Service Dependency** | Inventory | **HIGH** | Inventory deduction is not enforced by DB/Model; bypassing `FifoAllocatorService` creates "Ghost Allocations". |
| **Inventory Gap** | Corrections | Medium | "Price/Amount" Corrections are supported, but "Quantity" Corrections do not adjust inventory, leading to Stock Desync. |
| **Mutable History** | Reporting | Medium | "Closed" Daily Reports depend on live transaction data and can change if a day is Reopened and edited. |

---

## 2. Broken Invariants

### 2.1 "Double-Entry" Atomicity
- **Assumption**: Every financial document creation *atomically* updates the General Ledger.
- **Reality (Returns)**: **BROKEN (Double Impact)**. Cancelling a return hits the ledger twice.
- **Reality (Invoices)**: **BROKEN (Zero Impact)**. Bypassing Controller (e.g. Factory/Seeder) creates invoice with Zero ledger impact.

### 2.2 "Single Source of Truth"
- **Assumption**: A transaction's status (`cancelled`) determines its ledger impact.
- **Reality**: The *act of cancelling* (Service method) has side effects distinct from the *state of being cancelled* (Observer), leading to race conditions or duplication.

---

## 3. Workarounds Identified
1.  **Manual Seeder Updates**: `DemoSeeder` manually increments customer balances after creating invoices, patching the missing Observer logic.
2.  **Controller Orchestration**: `InvoiceController` manually handles the ledger update to skirt around the "zero-total on create" limitation.

---

## 4. Recommendations (Decision Required)

### CRITICAL (Fix Immediately)
1.  **Disable `ReturnObserver` Cancellation Logic**: The `ReturnService` already handles the transactional reversal. Remove the redundant logic in `ReturnObserver::updated` to prevent double-crediting.
2.  **Unify Ledger Updates**: Move Invoice Balance updates **into the Observer** (listening to `created` or `updated` with dirty checks) or strictly enforce a `TransactionService`.

### Strategic
1.  **Formalize Inventories**: Inventory constraints must be enforced at the Database level (Trigger) or Model level (Observer) to prevent "Ghost Allocations".
2.  **Lock Down Reports**: Implement a strict Policy denying `update/delete` on any transaction belonging to a `closed` Daily Report.

---

## 5. Decision Readiness
**Is this system safe to scale without architectural decisions?**
**NO.**
The **Double-Crediting Bug in Returns** is a show-stopper for production. The **Ghost Invoice Risk** is a show-stopper for any external integration (API/Import).

---
**References:**
- `AA-2025-12-27-returns-refunds-integrity.md` (CRITICAL)
- `AA-2025-12-27-invoice-creation-balance-updates.md` (HIGH)
- `AA-2025-12-27-inventory-fifo-integrity.md` (HIGH)
- `AA-2025-12-27-correction-module-integrity.md`
- `AA-2025-12-27-daily-report-consistency.md`
