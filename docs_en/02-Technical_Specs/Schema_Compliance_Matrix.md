# Schema → Backend Enforcement Matrix

**Date:** 2025-12-13
**Purpose:** Single reference mapping Database Schema to Backend Enforcement Layers.

| Legend | Meaning |
|--------|---------|
| ✔️ | **Fully Enforced** (Implemented in Plan) |
| ⚠️ | **Partial / Implicit** (Logic exists but needs explicit rule) |
| ❌ | **Missing** (No enforcement found) |

---

## 1. Invoices Table (`invoices`)

| Field / Rule | Business Meaning | Enforcement Layer | Status | Notes |
|--------------|------------------|-------------------|--------|-------|
| `invoice_number` | Unique ID | `InvoiceNumberGenerator` | ✔️ | Service handles generation. |
| `date` | Edit restriction | `InvoicePolicy::update` | ✔️ | "Edit Window" check. |
| `subtotal` | Calculation | Frontend / Backend Validation | ✔️ | Calculated from items. |
| `discount` | Discount < Subtotal | `StoreInvoiceRequest` | ❌ | Missing `lte:subtotal` rule. |
| `total` | Final Amount | `InvoiceObserver::updated` | ✔️ | `newTotal >= paid_amount` check. |
| `balance` | Paid Status | `InvoiceObserver` | ✔️ | Updated by Collections. |
| `status` | State (Active/Cancelled) | `InvoiceObserver::updated` | ✔️ | Handles cancellation logic. |
| `delete` | Deletion Policy | `InvoiceObserver::deleting` | ✔️ | Throws Exception (Soft delete by policy). |

---

## 2. Collections Table (`collections`)

| Field / Rule | Business Meaning | Enforcement Layer | Status | Notes |
|--------------|------------------|-------------------|--------|-------|
| `amount` | Payment Value | `StoreCollectionRequest` | ✔️ | `gt:0` validation. |
| `date` | Edit restriction | `CollectionPolicy::update` | ✔️ | "Edit Window" check. |
| `distribution` | FIFO Allocation | `CollectionService` | ✔️ | `oldest_first` implementation. |
| `concurrency` | Race Condition | `CollectionService` | ✔️ | `lockForUpdate()` in FIFO allocation. |
| `customer_balance`| Ledger Update | `CollectionObserver` | ✔️ | Decrements customer balance. |

---

## 3. Shipments Table (`shipments`)

| Field / Rule | Business Meaning | Enforcement Layer | Status | Notes |
|--------------|------------------|-------------------|--------|-------|
| `status` | Lifecycle | `ShipmentObserver` | ✔️ | Open → Closed → Settled. |
| `close` | Auto-Close | `ShipmentItemObserver` | ✔️ | Closes when all items 0. |
| `settle` | Finalize | `ShipmentService::settle` | ✔️ | Calculates totals. |
| `unsettle` | Revert | `ShipmentService::unsettle` | ✔️ | Reverses implementation. |
| `delete` | Safety | `ShipmentObserver` | ✔️ | Prevent if has invoices. |

---

## 4. Financial Transactions (`cashbox_transactions`)

| Field / Rule | Business Meaning | Enforcement Layer | Status | Notes |
|--------------|------------------|-------------------|--------|-------|
| `type` | Transaction Type | Validation | ✔️ | Enum validation. |
| `deposit` | Manual In | **None** | ❌ | No Controller. |
| `withdraw` | Manual Out | **None** | ❌ | No Controller. |
| `balance` | Running Total | `CashboxTransactionObserver` | ⚠️ | Logic mentioned but sparse. |

---

## 5. Inventory (`shipment_items`)

| Field / Rule | Business Meaning | Enforcement Layer | Status | Notes |
|--------------|------------------|-------------------|--------|-------|
| `remaining_qty` | FIFO Pool | `FifoAllocatorService` | ✔️ | Decrement logic. |
| `wastage` | Damaged Goods | **None** | ❌ | No direct adjust controller. |
| `adjust` | Stock Correction | **None** | ❌ | No direct adjust controller. |

---

## 6. System Wide

| Concept | Meaning | Enforcement Layer | Status | Notes |
|---------|---------|-------------------|--------|-------|
| **Authentication**| Access Control | `Sanctum` | ✔️ | Global middleware. |
| **Permissions** | Role Actions | Policies | ⚠️ | High-risk gaps (Finance/Inventory). |
| **Rate Limit** | DoS Protection | Middleware | ✔️ | API Throttling planned. |
