# Frontend Developer Documentation
## Frontend Developer Guide

---

## 📋 Overview

This document is intended for the Frontend developer to explain how to integrate with the Backend API.

---

## 🌐 Environment Configuration

### Development
```env
NEXT_PUBLIC_API_URL=http://localhost:8001/api
NEXT_PUBLIC_APP_NAME=Sales System
```

### Production (Hostinger Startup Cloud)
```env
NEXT_PUBLIC_API_URL=https://api.yourdomain.com/api
NEXT_PUBLIC_APP_NAME=Sales System
```

### URL Structure
| Environment | Frontend | Backend API |
|-------------|----------|-------------|
| **Development** | `http://localhost:3000` | `http://localhost:8001/api` |
| **Production** | `https://yourdomain.com` | `https://api.yourdomain.com/api` |

> ⚠️ **Note:** Replace `yourdomain.com` with actual domain

---

## 🎨 UI Specifications

| Item | Value |
|------|-------|
| **Language** | English only |
| **Currency** | QAR (ر.ق) |
| **Primary Color** | Blue (#0066CC or similar) |
| **Direction** | LTR |
| **Dark Mode** | Optional (not required) |

### Company Info
Fetched from API: `GET /api/settings`
- `company_name`
- `company_address`
- `company_phone`
- `company_logo`

---

## 📐 Formatting Rules

| Type | Format | Example |
|------|--------|---------|
| **Date** | `YYYY-MM-DD` | `2025-12-20` |
| **Money** | Thousands separator, 2 decimals | `1,234.56` |
| **Quantity** | Thousands separator, no decimals | `1,234` |
| **Currency Symbol** | After number | `1,234.56 QAR` |

---

## ⏳ Loading States

| Type | Usage |
|------|-------|
| **Spinner** | Use for ALL loading states |

No skeleton loading needed. Simple spinner for:
- Button submissions
- Form submissions
- Page loads
- Table loads

---

## 📝 UI Behavior

| Behavior | Decision |
|----------|----------|
| **Pagination** | Yes, 20 items per page |
| **Empty State** | "No data available" |
| **Error Messages** | From Backend (Arabic + English) |
| **Success Messages** | From Backend |
| **Confirm Dialogs** | No (except critical actions) |

---

## 📱 Mobile-First Priority

### 🔴 CRITICAL - Must be Mobile Optimized:

| Page | Priority | Notes |
|------|----------|-------|
| **Add Shipment** | 🔴 HIGH | Quick entry form |
| **Create Invoice** | 🔴 HIGH | Product selection, quick totals |
| **Create Collection** | 🔴 HIGH | Amount input, customer select |
| **Create Expense** | 🔴 HIGH | Simple form |
| **Account Transfer** | 🔴 HIGH | From/To/Amount only |

### 🟡 Desktop-Focused (Complex):

| Page | Notes |
|------|-------|
| Reports | Tables, PDFs |
| Settings | Full configuration |
| Users | Permissions matrix |
| Audit Log | Long lists |

---

## 🔧 API Information

| Info | Value |
|----------|--------|
| **Base URL** | `http://localhost:8001/api` |
| **Authentication** | Bearer Token (Sanctum) |
| **Content-Type** | `application/json` |
| **Accept** | `application/json` |

---

## 🔐 Authentication

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 1,
      "name": "Admin",
      "email": "admin@example.com",
      "permissions": ["invoices.view", "invoices.create", ...]
    }
  }
}
```

### Using Token
```http
GET /api/dashboard
Authorization: Bearer 1|abc123...
```

### Get Current User
```http
GET /api/auth/me
Authorization: Bearer TOKEN
```

### Logout
```http
POST /api/auth/logout
Authorization: Bearer TOKEN
```

---

## 📊 Dashboard

### Stats & Activity
```http
GET /api/dashboard
```

**Response:**
```json
{
  "customers_count": 50,
  "suppliers_count": 10,
  "products_count": 25,
  "total_receivables": 150000,
  "total_payables": 80000,
  "open_shipments": 3,
  "today_sales": 15000,
  "today_sales_count": 5,
  "today_collections": 10000,
  "today_expenses": 500,
  "today_net_cash": 9500,
  "top_debtors": [
    {"id": 1, "name": "Customer 1", "balance": 5000}
  ]
}
```

### Recent Activity
```http
GET /api/dashboard/activity
```

**Response:**
```json
{
  "invoices": [...],
  "collections": [...],
  "expenses": [...]
}
```

---

## 📅 Daily Report

### Current Status
```http
GET /api/daily/current
```

**Response (if open):**
```json
{
  "report": {
    "id": 1,
    "date": "2025-12-20",
    "status": "open",
    "opening_balance": 5000
  },
  "working_date": "2025-12-20"
}
```

**Response (if closed):**
```json
{
  "report": null,
  "message": "No open daily report"
}
```

### Available Dates
```http
GET /api/daily/available
```

### Open Day
```http
POST /api/daily/open
Content-Type: application/json

{
  "date": "2025-12-20"
}
```

### Close Day
```http
POST /api/daily/close
```

### Day Summary
```http
GET /api/reports/daily/2025-12-20
```

**Response:**
```json
{
  "date": "2025-12-20",
  "sales": {
    "count": 5,
    "total": 15000.50,
    "discount": 200
  },
  "collections": {
    "count": 3,
    "total": 10000,
    "cash": 7000,
    "bank": 3000
  },
  "expenses": {
    "count": 2,
    "total": 500,
    "cash": 300,
    "bank": 200,
    "supplier": 100,
    "company": 400
  },
  "net": {
    "cash": 6700
  }
}
```

### Download PDF
```http
GET /api/reports/daily/2025-12-20/pdf
```
Returns: PDF file

---

## 👥 Customers

### List
```http
GET /api/customers
GET /api/customers?search=Ahmed
GET /api/customers?is_active=1
GET /api/customers?per_page=20
```

### Create
```http
POST /api/customers
Content-Type: application/json

{
  "name": "New Customer",
  "phone": "0501234567",
  "address": "Address",
  "opening_balance": 0,
  "notes": ""
}
```

### Update
```http
PUT /api/customers/1
```

### Delete
```http
DELETE /api/customers/1
```

### Statement
```http
GET /api/reports/customer/1?date_from=2025-01-01&date_to=2025-12-31
```

**Response:**
```json
{
  "customer": {
    "id": 1,
    "code": "C-00001",
    "name": "Customer",
    "current_balance": 5000
  },
  "transactions": [
    {
      "type": "invoice",
      "date": "2025-12-15",
      "reference": "INV-00001",
      "debit": 500,
      "credit": 0,
      "balance": 500
    },
    {
      "type": "collection",
      "date": "2025-12-16",
      "reference": "COL-00001",
      "debit": 0,
      "credit": 200,
      "balance": 300
    }
  ]
}
```

---

## 🏭 Suppliers

### List
```http
GET /api/suppliers
```

### Create
```http
POST /api/suppliers
{
  "name": "New Supplier",
  "phone": "0501234567",
  "opening_balance": 0
}
```

### Statement
```http
GET /api/reports/supplier/1
```

---

## 📦 Products

### List
```http
GET /api/products
```

### Create
```http
POST /api/products
{
  "name": "Tilapia",
  "name_en": "Tilapia",
  "default_weight": 25,
  "category": "fish",
  "is_active": true
}
```

---

## 🚛 Shipments

### List
```http
GET /api/shipments
GET /api/shipments?status=open
GET /api/shipments?supplier_id=1
```

### Current Stock
```http
GET /api/shipments/stock
```

**Response:**
```json
[
  {
    "product_id": 1,
    "product_name": "Tilapia",
    "total_quantity": 50,
    "items": [
      {
        "id": 10,
        "shipment": {"number": "SHP-001"},
        "remaining_cartons": 30,
        "weight_per_unit": 25
      }
    ]
  }
]
```

### Create
```http
POST /api/shipments
{
  "supplier_id": 1,
  "date": "2025-12-20",
  "items": [
    {
      "product_id": 1,
      "cartons": 50,
      "weight_per_unit": 25,
      "unit_cost": 150
    }
  ]
}
```

### Close
```http
POST /api/shipments/1/close
```

### Settle
```http
POST /api/shipments/1/settle
{
  "next_shipment_id": 2
}
```

### Settlement Report PDF
```http
GET /api/reports/shipment/1/settlement/pdf
```

---

## 🧾 Invoices

### List
```http
GET /api/invoices
GET /api/invoices?customer_id=1
GET /api/invoices?date_from=2025-12-20&date_to=2025-12-20
GET /api/invoices?unpaid_only=1
```

### Create
```http
POST /api/invoices
{
  "customer_id": 1,
  "date": "2025-12-20",
  "items": [
    {
      "product_id": 1,
      "cartons": 3,
      "total_weight": 72.5,
      "price": 8.50
    }
  ],
  "discount": 0,
  "notes": ""
}
```

**Note:** FIFO allocation is automatic based on `product_id` and `cartons`

### Cancel
```http
POST /api/invoices/1/cancel
```

### Price Adjustment
```http
POST /api/invoices/1/price-adjustment
{
  "adjustments": [
    {
      "product_name": "Tilapia",
      "old_price": 50,
      "new_price": 45,
      "quantity": 10
    }
  ]
}
```

---

## 💰 Collections

### List
```http
GET /api/collections
GET /api/collections?customer_id=1
```

### Unpaid Invoices
```http
GET /api/collections/unpaid-invoices?customer_id=1
```

**Response:**
```json
{
  "invoices": [
    {"id": 1, "invoice_number": "INV-001", "total": 500, "balance": 200}
  ],
  "total_balance": 200
}
```

### Create
```http
POST /api/collections
{
  "customer_id": 1,
  "date": "2025-12-20",
  "amount": 1000,
  "payment_method": "cash",
  "notes": ""
}
```

---

## ↩️ Returns

### List
```http
GET /api/returns
```

### Create
```http
POST /api/returns
{
  "customer_id": 1,
  "original_invoice_id": 5,
  "items": [
    {
      "invoice_item_id": 10,
      "cartons": 2
    }
  ],
  "notes": "Damaged"
}
```

### Cancel
```http
POST /api/returns/1/cancel
```

---

## 💸 Expenses

### List
```http
GET /api/expenses
GET /api/expenses?type=company
GET /api/expenses?supplier_id=1
```

### Create
```http
POST /api/expenses
{
  "date": "2025-12-20",
  "description": "Electricity Bill",
  "type": "company",
  "amount": 500,
  "payment_method": "cash"
}
```

**Types:**
| Type | Description |
|------|-------------|
| `company` | Company Expense |
| `supplier` | Supplier Expense |
| `supplier_payment` | Payment to Supplier |

For `supplier` or `supplier_payment`, add `supplier_id`:
```json
{
  "type": "supplier_payment",
  "supplier_id": 1,
  "amount": 5000
}
```

---

## 📝 Credit Notes

### List
```http
GET /api/credit-notes
```

### Create Credit Note (Credit - Decrease)
```http
POST /api/credit-notes/credit
{
  "customer_id": 1,
  "invoice_id": 5,
  "amount": 50,
  "reason": "Price Adjustment"
}
```

### Create Debit Note (Debit - Increase)
```http
POST /api/credit-notes/debit
{
  "customer_id": 1,
  "amount": 30,
  "reason": "Delivery Fee"
}
```

### Cancel
```http
POST /api/credit-notes/1/cancel
```

---

## 🏦 Treasury

### Accounts Summary
```http
GET /api/accounts/summary
```

**Response:**
```json
{
  "cashbox": {
    "account": {...},
    "balance": 15000
  },
  "bank": {
    "account": {...},
    "balance": 50000
  },
  "total": 65000
}
```

### Cashbox
```http
GET /api/cashbox                    // Balance
GET /api/cashbox/transactions       // Transactions
POST /api/cashbox/deposit           // Deposit
POST /api/cashbox/withdraw          // Withdraw
```

### Bank
```http
GET /api/bank
GET /api/bank/transactions
POST /api/bank/deposit
POST /api/bank/withdraw
```

### Transfers
```http
GET /api/transfers
POST /api/transfers
{
  "from_account_id": 1,
  "to_account_id": 2,
  "amount": 5000,
  "notes": "Transfer to bank"
}
```

---

## 🚨 Alerts

### List
```http
GET /api/alerts
GET /api/alerts?unread_only=1
GET /api/alerts?type=price_anomaly
```

### Summary
```http
GET /api/alerts/summary
```

### Run Detection
```http
POST /api/alerts/run-detection
```

### Mark Read
```http
POST /api/alerts/1/read
```

### Resolve
```http
POST /api/alerts/1/resolve
```

---

## ⚙️ Settings

### Get All
```http
GET /api/settings
```

**Response:**
```json
{
  "company_name": "Fish Company",
  "currency_symbol": "QAR",
  "company_commission_rate": "6",
  "price_anomaly_threshold": "30"
}
```

### Update
```http
PUT /api/settings
{
  "company_name": "New Name"
}
```

---

## 👤 Users

### List
```http
GET /api/users
```
