# Frontend Adversarial Review & Security Audit

**Date**: 2025-12-27
**Reviewer**: Independent Principal Frontend Architect
**Scope**: Frontend Application (`/src/app`)
**Status**: Critical Fixes Required

---

## 1. Executive Summary

A comprehensive, code-first adversarial review of the frontend application was conducted to identify integrity guarantees, backend invariant violations, and operational deadlocks.

**Verdict**: The application core is functional, but **4 critical modules** lack essential safety guards, creating risks of financial data corruption ("Magic Stock"), operational deadlock (Closed Store), or privilege escalation.

| Metric | Count | Description |
|--------|-------|-------------|
| **Critical (P0)** | **7** | Issues requiring immediate fix before production. |
| **High (P1)** | **3** | Usability/Scaling issues to fix before V1. |
| **Deferred** | **3** | Risks mitigated by strict business constraints (Single Supplier/Bank). |

---

## 2. Critical Findings (P0) - Immediate Action Required

The following issues compromise the accounting ledger or system security.

### 2.1. Returns Module: "Magic Stock" Creation
- **Risk**: Users can create returns for *any* product at *any* price, without linking to an original sale. This allows "printing money" (refunding more than paid) and corrupting inventory COGS.
- **Fix Design**:
  1.  **Enforce Invoice Link**: User MUST select an existing Invoice for the Customer.
  2.  **Derive Items**: Returns items must be selected from the Invoice's original items.
  3.  **Cap Values**: Return Price = Original Sale Price. Max Return Qty = Original Sold Qty.

### 2.2. Collections Module: Deceptive Manual Allocation
- **Risk**: The "Manual Allocation" option exists in UI but provides no mechanism to actually map payments to invoices. This leads to legal/accounting disputes where customers believe they paid specific debts, but the system auto-allocates or leaves funds unapplied.
- **Fix Design**:
  1.  **Allocation Table**: When "Manual" is selected, render a table of Unpaid Invoices.
  2.  **Validation**: `Sum(Allocations)` must exactly equal `Payment Amount`.

### 2.3. IAM (Settings & Users): Open Administration Port
- **Risk**: Critical paths `/settings` (System Config, DB Reset) and `/users` (Create Admin) lack client-side Permission Gates.
- **Fix Design**:
  1.  **Wrap Pages**: Apply `<PermissionGate permission="admin.access">` to both pages.
  2.  **Server Verify**: Ensure backend strictly enforces policies (Double-check `UserPolicy` on API).

### 2.4. Daily Operations: Operational Deadlock
- **Risk**: If a Daily Report fails to close due to a backend validation error (e.g., negative stock), the shop is closed indefinitely with no UI to resolve it.
- **Fix Design**:
  1.  **Force Close**: Add an Admin-only "Force Close" action that bypasses soft validations to restore operations.

### 2.5. Accounts: Hardcoded IDs
- **Risk**: Transactions assume `Cashbox ID = 1` and `Bank ID = 2`.
- **Fix Design**: Dynamically fetch accounts by `type` ('cash', 'bank') before processing transfers.

---

## 3. High Priority (P1) - Recommended Before V1

1.  **Pagination**: List pages load all records. Will crash browsers after ~1000 transactions.
2.  **Customer Credit Limit**: No frontend enforcement of credit limits limits sales exposure.

---

## 4. Deferred Risks (Accepted Constraints)

The following potential issues are **accepted** due to explicit business constraints. They are safe *only* as long as these constraints hold.

| Module | Issue | Constraint Mitigating Risk | Future Action (if constraint changes) |
|--------|-------|----------------------------|---------------------------------------|
| **Shipments** | Cross-Supplier Carryover | **"Only ONE Supplier"** | If Multi-Supplier: Add filter to `Carryover` dropdown. |
| **Expenses** | Implicit Bank Source | **"Only ONE Bank Account"** | If Multi-Bank: Add Source Account Selector. |
| **Accounts** | Fixed Account Structure | **"Only TWO Accounts (Cash/Bank)"** | If Multi-Cashbox: Refactor Transfer UI. |

---

## 5. Implementation Plan

A dedicated branch `fix/critical-safety` should be created to address the P0s in the following order:

1.  **Safety Gates**: Lock down `/settings` and `/users`.
2.  **Returns Logic**: Rewrite `/returns/new` to enforce Invoice Linking.
3.  **Collections Logic**: Rewrite `/collections/new` to support true Manual Allocation.
4.  **Deadlock Prevention**: Add Force Close to `/daily`.

**Signed**: System Architect
**Date**: 2025-12-27

---

## 6. Critique & Residual Risks (Post-Review Analysis)

This section challenges the audit itself, identifying risks that persist even after the planned P0 fixes.

### 6.1. The "Double-Spend" Risk (Network Idempotency)
- **Gap**: Logic fixes do not prevent network retries.
- **Scenario**: A user on slow 4G clicks "Save" twice. Without idempotency keys, the backend may process two payments.
- **Mitigation**: Add `X-Request-ID` header to all financial mutations.

### 6.2. The Floating Point Trap
- **Gap**: JavaScript math (`0.1 + 0.2 !== 0.3`) may block valid Manual Allocations.
- **Mitigation**: Use a library like `decimal.js` or integer-based math for all client-side validation involving money.

### 6.3. Dropdown Scalability (Browser Bomb)
- **Gap**: Single Supplier constraint != Few Products. Loading 5,000 SKUs into a `<Select>` will freeze the UI.
- **Mitigation**: Implement virtualized lists (e.g., `cmdk`) for Product/Customer selectors.

### 6.4. Audit Blindspots ("God Mode")
- **Gap**: "Force Close" fixes the deadlock but bypasses audit trails.
- **Mitigation**: Require a mandatory "Reason" text field when using Force Close to log *why* the override was necessary.

