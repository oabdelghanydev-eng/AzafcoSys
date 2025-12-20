# Frontend Developer Documentation
## دليل مطور الواجهة الأمامية

---

## 📋 نظرة عامة

هذا المستند موجه لمطور الـ Frontend لشرح كيفية الربط مع الـ Backend API.

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
| **Currency** | ر.ق (QAR) |
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
| **Currency Symbol** | After number | `1,234.56 ر.ق` |

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

## 🔧 معلومات الـ API

| المعلومة | القيمة |
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
    {"id": 1, "name": "عميل 1", "balance": 5000}
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

## 📅 Daily Report (اليومية)

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
  "message": "لا توجد يومية مفتوحة"
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
GET /api/customers?search=أحمد
GET /api/customers?is_active=1
GET /api/customers?per_page=20
```

### Create
```http
POST /api/customers
Content-Type: application/json

{
  "name": "عميل جديد",
  "phone": "0501234567",
  "address": "العنوان",
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
    "name": "عميل",
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
  "name": "مورد جديد",
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
  "name": "سمك بلطي",
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

### Current Stock (المخزون المتاح)
```http
GET /api/shipments/stock
```

**Response:**
```json
[
  {
    "product_id": 1,
    "product_name": "بلطي",
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

### Price Adjustment (تسوية سعر)
```http
POST /api/invoices/1/price-adjustment
{
  "adjustments": [
    {
      "product_name": "بلطي",
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
  "notes": "تالف"
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
  "description": "فاتورة كهرباء",
  "type": "company",
  "amount": 500,
  "payment_method": "cash"
}
```

**Types:**
| Type | Description |
|------|-------------|
| `company` | مصروف شركة |
| `supplier` | مصروف على المورد |
| `supplier_payment` | دفعة للمورد |

For `supplier` or `supplier_payment`, add `supplier_id`:
```json
{
  "type": "supplier_payment",
  "supplier_id": 1,
  "amount": 5000
}
```

---

## 📝 Credit Notes (إشعارات التسوية)

### List
```http
GET /api/credit-notes
```

### Create Credit Note (دائن - تخفيض)
```http
POST /api/credit-notes/credit
{
  "customer_id": 1,
  "invoice_id": 5,
  "amount": 50,
  "reason": "تسوية سعر"
}
```

### Create Debit Note (مدين - زيادة)
```http
POST /api/credit-notes/debit
{
  "customer_id": 1,
  "amount": 30,
  "reason": "رسوم توصيل"
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
  "notes": "تحويل للبنك"
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
  "company_name": "شركة الأسماك",
  "currency_symbol": "ر.ق",
  "company_commission_rate": "6",
  "price_anomaly_threshold": "30"
}
```

### Update
```http
PUT /api/settings
{
  "company_name": "اسم جديد"
}
```

---

## 👤 Users

### List
```http
GET /api/users
```

### Permissions List
```http
GET /api/permissions
```

### Create
```http
POST /api/users
{
  "name": "مستخدم جديد",
  "email": "user@example.com",
  "password": "password123",
  "permissions": ["invoices.view", "invoices.create"]
}
```

### Update Permissions
```http
PUT /api/users/1/permissions
{
  "permissions": ["invoices.view"]
}
```

### Lock/Unlock
```http
POST /api/users/1/lock
POST /api/users/1/unlock
```

---

## 📋 Audit Log

### List
```http
GET /api/audit
GET /api/audit?user_id=1
GET /api/audit?action=create
```

### Trail
```http
GET /api/audit/trail?entity=Invoice&entity_id=5
```

---

## ⚠️ Error Handling

All errors return:
```json
{
  "success": false,
  "error": {
    "code": "INV_001",
    "message": "رسالة الخطأ بالعربي",
    "message_en": "Error message in English"
  }
}
```

**HTTP Status Codes:**
| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

---

## 🔑 Permissions

Check user permissions from `GET /api/auth/me`:
```javascript
const user = await api.get('/auth/me');
const canCreateInvoice = user.permissions.includes('invoices.create');
```

**Permission Categories:**
- `invoices.*` - الفواتير
- `collections.*` - التحصيلات
- `expenses.*` - المصروفات
- `shipments.*` - الشحنات
- `customers.*` - العملاء
- `suppliers.*` - الموردين
- `products.*` - المنتجات
- `returns.*` - المرتجعات
- `daily_reports.*` - اليومية
- `reports.*` - التقارير
- `settings.*` - الإعدادات
- `users.*` - المستخدمين
- `alerts.*` - التنبيهات
- `credit_notes.*` - إشعارات التسوية

---

## 📁 API Documentation

Interactive API documentation available at:
```
http://localhost:8001/docs/api
```

---

## ❌ Missing Endpoints (Coming Soon)

| Endpoint | Description |
|----------|-------------|
| `GET /invoices/{id}/pdf` | Invoice PDF |
| `GET /reports/customer/{id}/pdf` | Customer Statement PDF |
| `GET /reports/supplier/{id}/pdf` | Supplier Statement PDF |

---

## 📅 Last Updated
**2025-12-20**
