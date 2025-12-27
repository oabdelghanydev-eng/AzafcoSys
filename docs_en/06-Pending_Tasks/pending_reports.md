# Pending Tasks

## 📄 Required PDF Reports

### Phase 1 - Essentials (Before Frontend)

#### 1. Customer Invoice PDF ❌ Missing
- **Endpoint:** `GET /invoices/{id}/pdf`
- **Description:** Print customer invoice
- **Content:**
  - Company Details
  - Customer Details
  - Invoice Date and Number
  - Invoice Items (Product, Quantity, Price, Total)
  - Discount
  - Grand Total
  - Paid Amount and Remaining Balance

#### 2. Customer Statement PDF ❌ Missing
- **Endpoint:** `GET /reports/customer/{id}/pdf?date_from=&date_to=`
- **Description:** Detailed customer statement
- **Content:**
  - Customer Details
  - Period
  - Transactions Table (Date, Description, Debit, Credit, Balance)
  - Final Balance

#### 3. Supplier Statement PDF ❌ Missing
- **Endpoint:** `GET /reports/supplier/{id}/pdf?date_from=&date_to=`
- **Description:** Detailed supplier statement
- **Content:**
  - Supplier Details
  - Period
  - Transactions Table (Shipments, Expenses, Payments)
  - Final Balance

---

### Phase 2 - Additional Reports (Later)

| Report | Priority | Description |
|---------|----------|-------|
| Debts Report | High | Overdue customers |
| Suppliers Due Report | High | Amounts due to suppliers |
| Monthly Profit Report | Medium | Monthly P&L |
| Monthly Sales Report | Medium | Total sales per month |
| Sales by Product | Medium | Best-selling products |
| Sales by Customer | Medium | Top customers |
| Inventory Movement | Medium | Incoming and Outgoing per product |
| Wastage Report | Low | Wastage percentage per shipment/product |
| Returns Report | Low | Returns analysis |
| Monthly Collections | Low | Total Cash/Bank collections |
| Monthly Expenses | Low | Expenses analysis by type |
| Treasury Movement | Low | All cash transactions |
| Supplier Performance | Low | Shipment quality and wastage ratio |

---

## 📅 Last Update
- **Date:** 2025-12-20
- **Status:** Pending
