# Business Rules Catalogue

## 📋 Overview

This file is the **Main Reference** for all business rules in the system.
Each rule has a unique ID (BR-XXX) used in code and documentation.

---

## 🗂️ Categories

| Code | Category | Description |
|-------|-------|-------|
| BR-INV | Invoices | Invoice Rules |
| BR-COL | Collections | Collection Rules |
| BR-SHP | Shipments | Shipment Rules |
| BR-INV-FIFO | Inventory FIFO | Inventory Rules |
| BR-CUS | Customers | Customer Rules |
| BR-SUP | Suppliers | Supplier Rules |
| BR-ACC | Accounts | Cashbox/Bank Rules |
| BR-RPT | Reports | Report Rules |
| BR-SEC | Security | Security Rules |
| BR-VAL | Validation | Validation Rules |

---

## 📑 Invoices (BR-INV)

### BR-INV-001: Create Invoice
| Field | Value |
|-------|-------|
| **Description** | Customer balance updated automatically on invoice creation |
| **Rule** | `customer.balance += invoice.total` |
| **Trigger** | InvoiceObserver::created |
| **Conditions** | Invoice status = 'active' |

### BR-INV-002: Invoice Balance
| Field | Value |
|-------|-------|
| **Description** | Invoice Balance = Total - Paid |
| **Rule** | `invoice.balance = invoice.total - invoice.paid_amount` |
| **Update** | On creation and every collection |
| **Index** | Yes, for fast search of unpaid invoices |

### BR-INV-003: Cancel Invoice ⚠️ (Critical)
| Field | Value |
|-------|-------|
| **Description** | When cancelling invoice, handle Allocations first |
| **Steps** | 1️⃣ Unlink Allocations (Return amounts to collections) |
|            | 2️⃣ Decrease customer balance by **TOTAL** amount |
|            | 3️⃣ Zero out balance and paid_amount |
| **Rule** | `customer.balance -= invoice.total` |
| **Trigger** | InvoiceObserver::updated (status → cancelled) |
| **Execution** | `CollectionService::reverseAllocationsForInvoice($invoice)` |

> ⚠️ **Why Total not Balance?**
> Because we will cancel allocations, so paid amounts will return as credit balance to customer.
> Formula: `customer.balance -= total` then `allocations.delete` will increase balance by `paid_amount`
> Result: `balance = original - total + paid = original - (total - paid) = original - balance` ✅

### BR-INV-003b: Unlink on Cancel
| Field | Value |
|-------|-------|
| **Description** | Delete allocations linked to cancelled invoice |
| **Rule** | `invoice.allocations.each.delete()` |
| **Reason** | Prevent inconsistency: Cancelled invoice + Active allocations |
| **Effect** | Collection becomes unallocated (credit balance for customer) |

### BR-INV-004: Prevent Deletion Completely ⛔
| Field | Value |
|-------|-------|
| **Description** | Deletion is strictly forbidden - Use cancellation |
| **Rule** | `throw Exception("Cannot delete invoices")` |
| **Trigger** | InvoiceObserver::deleting |
| **Reason** | Cancellation preserves audit trail |
| **Error Code** | `INV_001` |

### BR-INV-005: Integrity Check on Update
| Field | Value |
|-------|-------|
| **Description** | Cannot reduce invoice total below paid amount |
| **Rule** | `if (new_total < paid_amount) throw Exception` |
| **Trigger** | InvoiceObserver::updated |
| **Message** | "Cannot reduce total below paid amount" |

### BR-INV-006: Edit Window
| Field | Value |
|-------|-------|
| **Description** | Editing allowed only within current day + specific window |
| **Rule** | `invoice.date >= now() - settings.edit_window_days` |
| **Trigger** | InvoicePolicy::update |
| **Setting** | `settings.edit_window_days` (default: 1) |

### BR-INV-007: Invoice Number Generation
| Field | Value |
|-------|-------|
| **Description** | Unique number following format defined in settings |
| **Rule** | `{prefix}-{year}{month}-{sequence}` |
| **Trigger** | InvoiceNumberGenerator::generate |
| **Settings** | prefix, format, sequence_length, reset_monthly |

---

## 📑 Collections (BR-COL)

### BR-COL-001: Create Collection
| Field | Value |
|-------|-------|
| **Description** | Customer balance reduced on collection creation |
| **Rule** | `customer.balance -= collection.amount` |
| **Trigger** | CollectionObserver::created |
| **Result** | Negative Balance = Credit to Customer |

### BR-COL-002: FIFO Distribution
| Field | Value |
|-------|-------|
| **Description** | Distribute collection amount to oldest invoices first |
| **Rule** | `ORDER BY date ASC WHERE balance > 0` |
| **Trigger** | CollectionService::allocatePayment |
| **Setting** | `settings.collection_distribution` |

### BR-COL-003: Record Allocation
| Field | Value |
|-------|-------|
| **Description** | Every allocation recorded in collection_allocations |
| **Rule** | Record for each invoice paid |
| **Fields** | collection_id, invoice_id, amount |

### BR-COL-004: Update Invoice on Allocation
| Field | Value |
|-------|-------|
| **Description** | When allocating amount to invoice |
| **Rule** | `invoice.paid_amount += amount; invoice.balance -= amount` |
| **Trigger** | CollectionAllocationObserver::created |

### BR-COL-005: Race Condition Protection
| Field | Value |
|-------|-------|
| **Description** | Prevent concurrent operation conflicts |
| **Rule** | `lockForUpdate()` on invoices |
| **Trigger** | CollectionService::allocatePayment |
| **Context** | DB::transaction |

### BR-COL-006: Cancel Collection ⚠️ (Correction 2025-12-13)
| Field | Value |
|-------|-------|
| **Description** | When cancelling collection, handle Allocations first |
| **Steps** | 1️⃣ Unlink Allocations |
|            | 2️⃣ Increase customer balance by amount |
| **Rule** | `customer.balance += collection.amount` |
| **Trigger** | CollectionObserver::updated (status → cancelled) |

### BR-COL-007: Prevent Deletion Completely ⛔ (Correction 2025-12-13)
| Field | Value |
|-------|-------|
| **Description** | Deletion is strictly forbidden - Use cancellation |
| **Rule** | `throw Exception("Cannot delete collections")` |
| **Trigger** | CollectionObserver::deleting |
| **Reason** | Cancellation preserves audit trail |
| **Error Code** | `COL_001` |

---

## 📑 Shipments (BR-SHP)

### BR-SHP-001: Shipment States
| Field | Value |
|-------|-------|
| **Description** | Shipment Status Flow |
| **Rule** | `open → closed → settled` |
| **Reverse** | `settled → closed` (Unsettle) |

### BR-SHP-002: Auto Close
| Field | Value |
|-------|-------|
| **Description** | Shipment closes automatically when depleted |
| **Rule** | `if (SUM(remaining_quantity) = 0) status = 'closed'` |
| **Trigger** | ShipmentItemObserver::updated |

### BR-SHP-003: Settlement
| Field | Value |
|-------|-------|
| **Description** | On settlement, remaining quantity carried to next shipment |
| **Rule** | Create Carryover + ShipmentItem in next shipment |
| **Trigger** | ShipmentController::settle |

### BR-SHP-004: Unsettle
| Field | Value |
|-------|-------|
| **Description** | Restore carried quantities to original shipment |
| **Rule** | Reverse carryover process |
| **Trigger** | ShipmentObserver::updated (settled → closed) |
| **Condition** | Quantity not sold from next shipment |

### BR-SHP-005: Unsettle Protection
| Field | Value |
|-------|-------|
| **Description** | Prevent Unsettle if carried quantity sold |
| **Rule** | `if (nextItem.remaining < carryover.quantity) throw Exception` |
| **Trigger** | ShipmentObserver::reverseCarryovers |
| **Message** | "Carried quantity has been sold from next shipment" |

### BR-SHP-006: Prevent Deleting Shipment with Invoices
| Field | Value |
|-------|-------|
| **Description** | Cannot delete shipment linked to invoices |
| **Rule** | Check linked invoice_items |
| **Trigger** | ShipmentObserver::deleting |

### BR-SHP-007: Prevent Editing Settled Shipment
| Field | Value |
|-------|-------|
| **Description** | Editing fields other than status is forbidden |
| **Rule** | Only status and updated_at allowed |
| **Trigger** | ShipmentObserver::updating |

---

## 📑 Inventory FIFO (BR-INV-FIFO)

### BR-FIFO-001: Quantity Allocation
| Field | Value |
|-------|-------|
| **Description** | Sell from oldest shipment first |
| **Rule** | `ORDER BY shipment.fifo_sequence ASC WHERE remaining_quantity > 0` |
| **Trigger** | FifoAllocatorService::allocate |
| **Note** | `fifo_sequence` immutable (Best Practice 2025) |

### BR-FIFO-002: Source Tracking
| Field | Value |
|-------|-------|
| **Description** | Each invoice item tracks FIFO source |
| **Rule** | `invoice_items.shipment_item_id` |
| **Benefit** | Track sales per shipment |

### BR-FIFO-003: Remaining Update
| Field | Value |
|-------|-------|
| **Description** | Deduct sold quantity from remaining |
| **Rule** | `shipment_item.remaining_quantity -= sold_quantity` |
| **Trigger** | On invoice_item creation |

---

## 📑 Customers (BR-CUS)

### BR-CUS-001: Balance Logic
| Field | Value |
|-------|-------|
| **Description** | Customer balance reflects financial state |
| **Rule** | `+` Debit (Owes us) / `0` Clear / `-` Credit (We owe them) |
| **Update** | Via Observers only |

### BR-CUS-002: Increase Balance
| Field | Value |
|-------|-------|
| **Description** | Balance increases on invoice creation |
| **Rule** | `balance += invoice.total` |

### BR-CUS-003: Decrease Balance
| Field | Value |
|-------|-------|
| **Description** | Balance decreases on collection or invoice cancellation |
| **Rule** | `balance -= amount` |

---

## 📑 Suppliers (BR-SUP)

### BR-SUP-001: Balance Logic
| Field | Value |
|-------|-------|
| **Description** | Supplier balance reflects debit |
| **Rule** | `+` Credit (We owe them) / `0` Clear / `-` Debit (They owe us) |

---

## 📑 Cashbox & Bank (BR-ACC)

### BR-ACC-001: Update Balance
| Field | Value |
|-------|-------|
| **Description** | Account balance updated on every transaction |
| **Rule** | `account.balance += transaction.amount` |
| **Trigger** | CashboxTransactionObserver / BankTransactionObserver |

### BR-ACC-002: Transfer checks
| Field | Value |
|-------|-------|
| **Description** | Transfer creates two opposing transactions |
| **Rule** | Withdrawal from source + Deposit to destination |

---

## 📑 Reports (BR-RPT)

### BR-RPT-001: Daily Report
| Field | Value |
|-------|-------|
| **Description** | Daily summary of all operations |
| **Content** | Sales, Collections, Expenses, Balances |
| **Generation** | Manual or Automatic at end of day |

### BR-RPT-002: Shipment Settlement
| Field | Value |
|-------|-------|
| **Description** | Detailed report per shipment |
| **Content** | Quantities, Sales, Returns, Wastage, Carryover |

---

## 📑 Security (BR-SEC)

### BR-SEC-001: Account Lock
| Field | Value |
|-------|-------|
| **Description** | Lock account after failed attempts |
| **Rule** | 3 failed attempts = Lock |
| **Unlock** | Admin Only |

### BR-SEC-002: Audit Logging
| Field | Value |
|-------|-------|
| **Description** | Every operation logged in audit_logs |
| **Content** | User, Type, Old, New, Timestamp |

---

## 📑 Validation (BR-VAL)

### BR-VAL-001: Edit Window
| Field | Value |
|-------|-------|
| **Description** | Editing allowed within specific time window |
| **Rule** | Today + Days defined in settings |
| **Applies To** | Invoices, Collections, Expenses |

### BR-VAL-002: Required Fields
| Field | Value |
|-------|-------|
| **Description** | Validation of required fields |
| **Trigger** | FormRequest in Laravel |

---

## 📑 Returns (BR-RET)

### BR-RET-001: Create Return
| Field | Value |
|-------|-------|
| **Description** | Return creates new record (Does not edit invoice) |
| **Effect** | 1️⃣ Increase Inventory 2️⃣ Decrease Customer Balance |
| **Trigger** | ReturnObserver::created |

### BR-RET-002: Late Return
| Field | Value |
|-------|-------|
| **Description** | Return from settled shipment |
| **Rule** | Carry over goods to current open shipment |
| **Trigger** | ReturnService::processLateReturn |

### BR-RET-003: Update Inventory
| Field | Value |
|-------|-------|
| **Description** | Increase remaining_quantity in shipment |
| **Rule** | `shipment_item.remaining_quantity += return.quantity` |
| **Trigger** | ReturnObserver::created |

### BR-RET-004: Update Customer Balance
| Field | Value |
|-------|-------|
| **Description** | Reduce debt |
| **Rule** | `customer.balance -= return.total_amount` |
| **Trigger** | ReturnObserver::created |

---

## 📅 Daily Reports (BR-DAY)

### BR-DAY-001: Open Daily Session
| Field | Value |
|-------|-------|
| **Description** | Must open day before any operation |
| **Rule** | User selects date → Saved in Session |
| **Opening Balance** | Last Closing Balance |
| **API** | `POST /api/daily/open` |

### BR-DAY-002: Operations Use Session Date
| Field | Value |
|-------|-------|
| **Description** | All operations take open session date automatically |
| **Rule** | `operation.date = session('working_date')` |
| **Benefit** | No need to enter date for every operation |

### BR-DAY-003: Available Dates
| Field | Value |
|-------|-------|
| **Description** | Determine which dates can be opened |
| **Range** | `today - backdated_days` to `today` |
| **Exclusion** | Closed dates (status = 'closed') |
| **Setting** | `backdated_days` (default: 2) |

### BR-DAY-004: Prevent Work Without Open Session
| Field | Value |
|-------|-------|
| **Description** | Cannot create invoice/collection without opening day |
| **Rule** | `if (!session('working_date')) throw` |
| **Middleware** | `EnsureWorkingDay` |

### BR-DAY-005: Close Session
| Field | Value |
|-------|-------|
| **Description** | Close day manually at end of shift |
| **Permission** | `daily.close` |
| **Effect** | Calculate Totals + Closing Balance + status = 'closed' |

### BR-DAY-006: Reopen Closed Session
| Field | Value |
|-------|-------|
| **Description** | Reopen closed day for corrections |
| **Permission** | `daily.reopen` |
| **Usage** | Exceptional for corrections only |

---

## 📄 Reports (Reports)

### BR-RPT-001: Daily Closing Report
| Field | Value |
|-------|-------|
| **Description** | Automatically created on day close |
| **Content** | Invoices + Collections + Expenses + Transfers + Balances |
| **Format** | PDF |

### BR-RPT-002: Shipment Settlement Report
| Field | Value |
|-------|-------|
| **Description** | Created on shipment settlement |
| **Content** | Sales + Returns + Stock + Supplier Account |
| **Format** | PDF |

### BR-RPT-003: Company Commission Calculation
| Field | Value |
|-------|-------|
| **Description** | Commission calculated on Net Sales |
| **Rule** | `Net Sales = Total Sales - Previous Shipment Returns` |
| **Calculation** | `Commission = Net Sales × company_commission_rate%` |
| **Setting** | `company_commission_rate` (default: 6) |

### BR-RPT-004: Supplier Payments
| Field | Value |
|-------|-------|
| **Description** | Recorded as special expense type |
| **Type** | `expenses.type = 'supplier_payment'` |
| **Effect** | Deducted from supplier balance in settlement |

---

## 📊 Statistics

| Category | Rules Count |
|-------|-------------|
| Invoices | 7 |
| Collections | 5 |
| Shipments | 7 |
| FIFO | 3 |
| Customers | 3 |
| Suppliers | 1 |
| Accounts | 2 |
| Security | 2 |
| Validation | 2 |
| Returns | 4 |
| **Daily Reports** | **6** |
| **Reports** | **4** |
| **Total** | **46** |

---

## 🔗 Related Files

- [BL_Invoices.md](BL_Invoices.md) - Invoice Logic
- [BL_Collections.md](BL_Collections.md) - Collection Logic
- [BL_Shipments.md](BL_Shipments.md) - Shipment Logic
- [BL_Refunds.md](BL_Refunds.md) - Refund Logic
- [BL_DailyReports.md](BL_DailyReports.md) - Daily Report Logic
- [BL_Users.md](BL_Users.md) - User Management Logic
- [BL_Corrections.md](BL_Corrections.md) - Correction Logic
