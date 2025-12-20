# Documentation Architecture Review

**Date:** 2025-12-13
**Reviewer:** Senior CTO
**Scope:** Architecture, Source of Truth, and Gaps.

---

## 1. Documentation Structure Review

The system documentation is generally coherent but requires a stricter "Source of Truth" hierarchy to prevent drift.

### Current vs Recommended Structure

| Layer | Recommended Files | Responsibilities | Status |
|-------|-------------------|------------------|--------|
| **1. Core / Source of Truth** | `Database_Schema.md` | Single source for Data Structure, Table Relationships, and Enum Values. All other docs must reference this. | ✅ Solid |
| **2. Architecture** | `Architecture_plan.md` | Infrastructure, Deployment, and High-level component interactions. | ✅ Good |
| **3. Backend Plan** | `Backend_Plan.md` | Implementation Plan, Class Structures, Services, and Observers. | ✅ Detailed |
| **4. Business Logic** | `Business_Logic/*.md` | Detailed business rules for each module (Invoices, Collections, Shipments, etc.). | ✅ Comprehensive |
| **5. Compliance & Security** | `backend_compliance_findings.md` <br> `authorization_coverage_review.md` | Tracking deviations, risks, and security coverage. | 🔄 In Progress |

---

## 2. Source of Truth: `Database_Schema.md`

This file is the undisputed source of truth.
- **22 Tables Verified:** Users, Customers, Suppliers, Products, Shipments, Items, Carryovers, Invoices, Collections, Allocations, Expenses, Transactions, Reports, Settings, Logs, Alerts.
- **Embedded Logic:** The schema comments (e.g., `balance` logic) serve as the foundation for the Observer logic.

**Schema → Backend Mapping Highlights:**
- **Observers:** Strongly mapped. Schema comments explicitly drive the logic in `InvoiceObserver` and `CollectionObserver`.
- **Enums:** `type`, `status`, `payment_method` in Schema are perfectly replicated in Backend validation rules.

---

## 3. Documentation Gaps & Risks

### A. Backend Enforcement Gaps (Medium Risk)
identified in `backend_compliance_findings.md`:
1.  **Collection Race Condition:** `lockForUpdate()` is documented as required but missing in `CollectionService` plan.
2.  **Explicit Validation:** `discount <= subtotal` rule is missing in `StoreInvoiceRequest`.

### B. Authorization Gaps (High Risk)
identified in `authorization_coverage_review.md`:
1.  **Financial Operations:** `cashbox.*` and `bank.*` permissions exist in Schema but have **NO** corresponding Controllers/Policies in the Backend Plan.
2.  **Inventory Adjustment:** `inventory.adjust` permission exists but has no implementation plan.

### C. Logic Clarification Needs (Low Risk)
1.  **Report Generation:** The logic for `DailyReport` generation (opening/closing/net) is defined in Schema but the `ReportService` implementation details are sparse in `Backend_Plan.md`.
2.  **Observer Details:** `ExpenseObserver`, `CashboxTransactionObserver` logic is mentioned but not fully detailed in the Plan compared to Invoices/Collections.

---

## 4. Clear Next Actions

1.  **P1 - Fix Financial Auth:** Update `Backend_Plan.md` to include `TransactionController` to handle manual cashbox/bank operations and link them to `cashbox.*` permissions.
2.  **P1 - Race Condition:** Explicitly add `lockForUpdate()` logic to `CollectionService` in `Backend_Plan.md`.
3.  **P2 - Inventory Adjustments:** Define `InventoryController` for manual stock adjustments (`inventory.adjust`).
4.  **P3 - Validation Rules:** Add `lte:subtotal` to Discount validation in `StoreInvoiceRequest`.
5.  **P3 - Matrix Update:** Maintain `Schema_Backend_Matrix.md` as a living document to track compliance.
