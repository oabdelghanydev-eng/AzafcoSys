# Entity Relationship Diagram - Sales and Shipments Management System

## 📊 Comprehensive ERD

```mermaid
erDiagram
    %% ========== USERS & AUTH ==========
    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar password
        varchar google_id UK
        json permissions
        boolean is_admin
        int failed_login_attempts
        boolean is_locked
        timestamp locked_at
        bigint locked_by FK
    }

    %% ========== CUSTOMERS & SUPPLIERS ==========
    customers {
        bigint id PK
        varchar name
        varchar phone
        varchar address
        decimal balance "Customer Balance"
        boolean is_active
        bigint created_by FK
    }

    suppliers {
        bigint id PK
        varchar name
        varchar phone
        varchar address
        decimal balance "Supplier Balance"
        boolean is_active
        bigint created_by FK
    }

    %% ========== PRODUCTS ==========
    products {
        bigint id PK
        varchar code UK
        varchar name
        decimal default_price
        boolean is_active
    }

    %% ========== SHIPMENTS ==========
    shipments {
        bigint id PK
        varchar shipment_number UK
        bigint supplier_id FK
        date date
        enum status "open|closed|settled"
        decimal total_weight
        decimal total_cost
        timestamp settled_at
        bigint created_by FK
    }

    shipment_items {
        bigint id PK
        bigint shipment_id FK
        bigint product_id FK
        int cartons
        decimal weight_per_unit
        varchar weight_label
        decimal initial_quantity
        decimal remaining_quantity "For FIFO"
        decimal sold_quantity
        decimal wastage_quantity
        decimal carryover_in_quantity
        decimal carryover_out_quantity
    }

    carryovers {
        bigint id PK
        bigint from_shipment_id FK
        bigint from_shipment_item_id FK
        bigint to_shipment_id FK
        bigint to_shipment_item_id FK
        bigint product_id FK
        decimal quantity
        enum reason "settlement|late_return"
        text notes
        bigint created_by FK
    }

    %% ========== INVOICES ==========
    invoices {
        bigint id PK
        varchar invoice_number UK
        bigint customer_id FK
        date date
        enum type "sale|wastage"
        decimal subtotal
        decimal discount
        decimal total
        decimal paid_amount
        decimal balance "Remaining Balance"
        enum status "active|cancelled"
        bigint created_by FK
    }

    invoice_items {
        bigint id PK
        bigint invoice_id FK
        bigint product_id FK
        bigint shipment_item_id FK "FIFO Source"
        bigint shipment_id FK
        int cartons
        decimal quantity "Weight (KG)"
        decimal price_per_kg "Price/KG"
        decimal total
        boolean is_wastage
    }

    %% ========== RETURNS (NEW) ==========
    returns {
        bigint id PK
        varchar return_number UK
        bigint customer_id FK
        bigint original_invoice_id FK
        date date
        decimal total_amount
        enum status "active|cancelled"
        text notes
        bigint created_by FK
    }

    return_items {
        bigint id PK
        bigint return_id FK
        bigint product_id FK
        bigint original_invoice_item_id FK
        bigint target_shipment_item_id FK
        decimal quantity
        decimal price_per_kg
        decimal subtotal
    }

    %% ========== COLLECTIONS ==========
    collections {
        bigint id PK
        varchar receipt_number UK
        bigint customer_id FK
        date date
        decimal amount
        enum payment_method "cash|bank"
        enum distribution_method "oldest_first|newest_first|manual"
        bigint invoice_id FK "For manual linking"
        enum status "confirmed|cancelled"
        bigint created_by FK
    }

    collection_allocations {
        bigint id PK
        bigint collection_id FK
        bigint invoice_id FK
        decimal amount "Allocated Amount"
    }

    %% ========== EXPENSES ==========
    expenses {
        bigint id PK
        varchar expense_number UK
        enum type "supplier|company|supplier_payment"
        bigint supplier_id FK
        bigint category_id FK
        date date
        decimal amount
        enum payment_method "cash|bank"
        text description
        enum status "confirmed|cancelled"
        bigint created_by FK
    }

    expense_categories {
        bigint id PK
        varchar name
        enum type "supplier|company"
        boolean is_active
    }

    %% ========== FINANCIAL ==========
    accounts {
        bigint id PK
        enum type "cashbox|bank"
        varchar name
        decimal balance
        boolean is_default
    }

    cashbox_transactions {
        bigint id PK
        bigint account_id FK
        enum type "collection|expense|transfer_in|transfer_out|deposit|withdraw"
        decimal amount
        decimal balance_after
        bigint reference_id
        varchar reference_type
        text notes
        bigint created_by FK
    }

    bank_transactions {
        bigint id PK
        bigint account_id FK
        enum type "collection|expense|transfer_in|transfer_out|deposit|withdraw"
        decimal amount
        decimal balance_after
        bigint reference_id
        varchar reference_type
        text notes
        bigint created_by FK
    }

    transfers {
        bigint id PK
        bigint from_account_id FK
        bigint to_account_id FK
        decimal amount
        date date
        text notes
        bigint created_by FK
    }

    %% ========== REPORTS & SETTINGS ==========
    daily_reports {
        bigint id PK
        date date UK
        decimal total_sales
        decimal total_collections_cash
        decimal total_collections_bank
        decimal total_expenses_cash
        decimal total_expenses_bank
        decimal cashbox_opening
        decimal cashbox_closing
        decimal bank_opening
        decimal bank_closing
        enum status "open|closed"
        bigint closed_by FK
    }

    settings {
        bigint id PK
        varchar key UK
        text value
        enum type "string|number|boolean|json"
        varchar description
    }

    audit_logs {
        bigint id PK
        bigint user_id FK
        varchar model_type
        bigint model_id
        varchar action
        json old_values
        json new_values
        varchar ip_address
        varchar user_agent
    }

    ai_alerts {
        bigint id PK
        enum type "price_anomaly|shipment_delay|fifo_error|customer_risk"
        varchar title
        text description
        json context
        enum status "new|read|resolved"
        bigint resolved_by FK
    }

    %% ========== RELATIONSHIPS ==========
    
    %% Users
    users ||--o{ invoices : "creates"
    users ||--o{ collections : "creates"
    users ||--o{ expenses : "creates"
    users ||--o{ shipments : "creates"
    users ||--o{ audit_logs : "generates"
    users ||--o{ customers : "creates"
    users ||--o{ suppliers : "creates"

    %% Customers
    customers ||--o{ invoices : "receives"
    customers ||--o{ collections : "pays"
    customers ||--o{ returns : "returns"

    %% Suppliers
    suppliers ||--o{ shipments : "sends"
    suppliers ||--o{ expenses : "supplier expenses"

    %% Products
    products ||--o{ shipment_items : "stocked in"
    products ||--o{ invoice_items : "sold as"
    products ||--o{ return_items : "returned as"
    products ||--o{ carryovers : "carried over"

    %% Shipments
    shipments ||--o{ shipment_items : "contains"
    shipments ||--o{ carryovers : "from shipment"
    shipments ||--o{ carryovers : "to shipment"

    %% Shipment Items
    shipment_items ||--o{ invoice_items : "FIFO source"
    shipment_items ||--o{ carryovers : "from item"
    shipment_items ||--o{ carryovers : "to item"
    shipment_items ||--o{ return_items : "return target"

    %% Invoices
    invoices ||--o{ invoice_items : "has"
    invoices ||--o{ collection_allocations : "paid by"
    invoices ||--o{ returns : "original invoice"

    %% Invoice Items
    invoice_items ||--o{ return_items : "original item"

    %% Returns
    returns ||--o{ return_items : "contains"

    %% Collections
    collections ||--o{ collection_allocations : "distributes"

    %% Expenses
    expense_categories ||--o{ expenses : "categorizes"

    %% Financial
    accounts ||--o{ cashbox_transactions : "has"
    accounts ||--o{ bank_transactions : "has"
    accounts ||--o{ transfers : "from"
    accounts ||--o{ transfers : "to"

    %% Daily Reports
    daily_reports ||--o{ users : "closed by"
```

---

## 📋 Tables Summary

| Category | Tables | Count |
|-------|---------|-------|
| **Users & Auth** | users | 1 |
| **Master Data** | customers, suppliers, products | 3 |
| **Shipments** | shipments, shipment_items, carryovers | 3 |
| **Sales** | invoices, invoice_items | 2 |
| **Returns** | returns, return_items | 2 |
| **Collections** | collections, collection_allocations | 2 |
| **Expenses** | expenses, expense_categories | 2 |
| **Financial** | accounts, cashbox_transactions, bank_transactions, transfers | 4 |
| **System** | daily_reports, settings, audit_logs, ai_alerts | 4 |
| **Total** | | **23** |

---

## 🔗 Key Relationships

### FIFO Flow
```
shipments → shipment_items → invoice_items → invoices
                ↓
            carryovers (settlement/late_return)
```

### Payment Flow
```
customers → invoices → collection_allocations ← collections
                              ↓
                    cashbox_transactions / bank_transactions
```

### Return Flow
```
invoices → invoice_items → return_items → returns
                ↓
         shipment_items (remaining_quantity++)
```

---

## 🎯 Important Notes

1. **Deletion Forbidden** for invoices and collections - use Cancellation
2. **FIFO** ordered by `shipments.date`, not `created_at`
3. **Calculation:** `total = quantity(kg) × price_per_kg`
4. **48 Permissions** distributed across 9 modules
