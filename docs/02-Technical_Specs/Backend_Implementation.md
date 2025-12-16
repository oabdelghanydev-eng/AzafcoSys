# Laravel Backend Development Plan

## 📋 Project Overview

| Item | Value |
|------|-------|
| Framework | Laravel 12 |
| PHP Version | 8.2+ |
| Database | MySQL 8.0 |
| Auth | Laravel Sanctum + Google OAuth |
| Development | Laragon (Windows) |
| Production | Hostinger |

---

## 🗂️ Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── SupplierController.php
│   │   │   ├── ShipmentController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── CollectionController.php
│   │   │   ├── ExpenseController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SettingController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── CashboxController.php
│   │   │   ├── BankController.php
│   │   │   ├── TransferController.php
│   │   │   ├── UserController.php
│   │   │   ├── DailyReportController.php
│   │   │   └── AuditLogController.php (Admin only)
│   │   ├── Requests/Api/
│   │   │   ├── StoreInvoiceRequest.php
│   │   │   ├── StoreCollectionRequest.php
│   │   │   ├── StoreCustomerRequest.php
│   │   │   ├── UpdateCustomerRequest.php
│   │   │   ├── StoreSupplierRequest.php
│   │   │   ├── UpdateSupplierRequest.php
│   │   │   ├── StoreExpenseRequest.php
│   │   │   ├── StoreTransferRequest.php
│   │   │   └── ...
│   │   ├── Resources/
│   │   │   ├── InvoiceResource.php
│   │   │   ├── CustomerResource.php
│   │   │   └── ...
│   │   └── Middleware/
│   │       └── EnsureWorkingDay.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── Supplier.php
│   │   ├── Product.php
│   │   ├── Shipment.php / ShipmentItem.php
│   │   ├── Invoice.php / InvoiceItem.php
│   │   ├── Collection.php / CollectionAllocation.php
│   │   ├── Expense.php
│   │   ├── Account.php / CashboxTransaction.php / BankTransaction.php
│   │   ├── Transfer.php
│   │   ├── DailyReport.php
│   │   ├── Setting.php
│   │   ├── AuditLog.php
│   │   └── AiAlert.php
│   ├── Observers/
│   │   ├── InvoiceObserver.php
│   │   ├── CollectionObserver.php
│   │   ├── CollectionAllocationObserver.php
│   │   ├── ShipmentObserver.php
│   │   ├── ShipmentItemObserver.php
│   │   ├── ReturnObserver.php
│   │   └── ExpenseObserver.php
│   ├── Services/
│   │   ├── CollectionService.php (FIFO allocation)
│   │   ├── FifoAllocatorService.php (Inventory FIFO)
│   │   ├── NumberGeneratorService.php
│   │   ├── DailyReportService.php
│   │   ├── AuditService.php
│   │   └── Reports/
│   │       ├── DailyClosingReportService.php
│   │       ├── ShipmentSettlementReportService.php
│   │       └── PdfGeneratorService.php
│   ├── Traits/
│   │   └── ApiResponse.php (checkPermission, ensureAdmin, success, error)
│   ├── Policies/
│   │   ├── InvoicePolicy.php (edit window)
│   │   ├── CollectionPolicy.php
│   │   ├── ShipmentPolicy.php
│   │   ├── UserPolicy.php
│   │   └── DailyReportPolicy.php
│   ├── Exceptions/
│   │   ├── BusinessException.php
│   │   └── ErrorCodes.php
│   └── Providers/
│       └── AppServiceProvider.php (observers, policies)
├── database/
│   ├── migrations/
│   └── seeders/
│       └── InitialDataSeeder.php (products, accounts, settings)
└── routes/
    └── api.php
```

---

## 📦 Phase 1: Foundation (Week 1)

### 1.1 Project Setup
```bash
laravel new backend
cd backend
composer require laravel/sanctum
composer require socialiteproviders/google
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
```

### 1.2 Database Migrations
| Order | Migration | Tables |
|-------|-----------|--------|
| 1 | create_users_table | users |
| 2 | create_customers_table | customers |
| 3 | create_suppliers_table | suppliers |
| 4 | create_products_table | products |
| 5 | create_shipments_table | shipments |
| 6 | create_shipment_items_table | shipment_items |
| 7 | create_carryovers_table | carryovers |
| 8 | create_invoices_table | invoices |
| 9 | create_invoice_items_table | invoice_items |
| 10 | create_collections_table | collections |
| 11 | create_collection_allocations_table | collection_allocations |
| 12 | create_expenses_table | expenses |
| 13 | create_accounts_table | accounts |
| 14 | create_cashbox_transactions_table | cashbox_transactions |
| 15 | create_bank_transactions_table | bank_transactions |
| 16 | create_transfers_table | transfers |
| 17 | create_daily_reports_table | daily_reports |
| 18 | create_settings_table | settings |
| 19 | create_audit_logs_table | audit_logs |
| 20 | create_ai_alerts_table | ai_alerts |

### 1.3 Seeders
```php
// ProductSeeder.php - 9 Fixed Products
$products = [
    ['code' => 'PRD-001', 'name' => 'صنف 1', 'default_price' => 0],
    // ... 9 products
];

// AccountSeeder.php
Account::create(['type' => 'cashbox', 'name' => 'الخزنة الرئيسية']);
Account::create(['type' => 'bank', 'name' => 'البنك الرئيسي']);

// SettingSeeder.php - All 17 settings from Database_Schema.md
```

---

## 📦 Phase 2: Core Models & Relationships (Week 1-2)

### Model Relationships Map

```
User
├── hasMany → invoices (created_by)
├── hasMany → collections (created_by)
├── hasMany → expenses (created_by)
└── hasMany → auditLogs

Customer
├── hasMany → invoices
├── hasMany → collections
└── balance (single column: +/0/-)

Supplier
├── hasMany → shipments
├── hasMany → expenses
└── balance (single column)

Shipment
├── belongsTo → supplier
├── hasMany → shipmentItems
├── hasMany → carryoversFrom (from_shipment_id)
└── hasMany → carryoversTo (to_shipment_id)

Invoice
├── belongsTo → customer
├── hasMany → invoiceItems
├── hasMany → collectionAllocations
└── balance (indexed, updated via Observer)

Collection
├── belongsTo → customer
├── hasMany → allocations
└── Observer handles FIFO distribution
```

---

## 📦 Phase 3: Observers Implementation (Week 2)

### Observer Registration
```php
// AppServiceProvider.php
public function boot(): void
{
    Invoice::observe(InvoiceObserver::class);
    Collection::observe(CollectionObserver::class);
    CollectionAllocation::observe(CollectionAllocationObserver::class);
    Shipment::observe(ShipmentObserver::class);
    ShipmentItem::observe(ShipmentItemObserver::class);
    Expense::observe(ExpenseObserver::class);
}
```

### Observers Priority
| Order | Observer | Complexity |
|-------|----------|------------|
| 1 | ShipmentItemObserver | Low - auto-close shipment |
| 2 | ShipmentObserver | High - Unsettle logic |
| 3 | InvoiceObserver | High - cancellation, balance |
| 4 | CollectionAllocationObserver | Medium - invoice balance |
| 5 | CollectionObserver | Medium - customer balance |
| 6 | ExpenseObserver | Low - supplier balance |

---

## 📦 Phase 4: Services (Week 2-3)

### 4.1 CollectionService (FIFO Payment)
```php
class CollectionService
{
    public function allocatePayment(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            $invoices = Invoice::where('customer_id', $collection->customer_id)
                ->where('balance', '>', 0)
                ->where('status', 'active')
                ->orderBy('date', 'asc')
                ->lockForUpdate()
                ->get();
            
            // FIFO allocation logic...
        });
    }
}
```

### 4.2 FifoAllocatorService (Inventory)
```php
class FifoAllocatorService
{
    public function allocateQuantity(int $productId, float $quantity): array
    {
        // Find oldest shipment_items with remaining_quantity
        // Deduct and return allocation array
    }
}
```

### 4.3 InvoiceNumberGenerator
```php
class InvoiceNumberGenerator
{
    public function generate(): string
    {
        $prefix = Setting::get('invoice_number_prefix', 'INV');
        $format = Setting::get('invoice_number_format', '{prefix}-{year}{month}-{sequence}');
        $length = (int) Setting::get('invoice_number_sequence_length', 4);
        // Generate based on format...
    }
}
```

---

## 📦 Phase 5: API Routes (Week 3)

### Route Groups
```php
// routes/api.php
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::get('google/redirect', [GoogleController::class, 'redirect']);
    Route::get('google/callback', [GoogleController::class, 'callback']);
    Route::post('logout', [LogoutController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    // Customers
    Route::apiResource('customers', CustomerController::class);
    
    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);
    
    // Shipments
    Route::apiResource('shipments', ShipmentController::class);
    Route::post('shipments/{shipment}/settle', [ShipmentController::class, 'settle']);
    Route::post('shipments/{shipment}/unsettle', [ShipmentController::class, 'unsettle']);
    
    // Invoices (❌ no DELETE - use cancel instead)
    Route::apiResource('invoices', InvoiceController::class)->except(['destroy']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    
    // Collections (❌ no DELETE - use cancel instead)
    Route::apiResource('collections', CollectionController::class)->except(['destroy']);
    Route::post('collections/{collection}/cancel', [CollectionController::class, 'cancel']);
    
    // Expenses
    Route::apiResource('expenses', ExpenseController::class);
    
    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('daily/{date}', [ReportController::class, 'daily']);
        Route::get('shipment/{shipment}', [ReportController::class, 'shipmentSettlement']);
        Route::get('customer/{customer}', [ReportController::class, 'customerStatement']);
    });
    
    // Settings (Admin only)
    Route::middleware('permission:manage_settings')->group(function () {
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
    });
});
```

---

## 📦 Phase 6: Validation & Policies (Week 3)

### Request Validation
```php
// UpdateInvoiceRequest.php
public function rules(): array
{
    return [
        'total' => [
            'required',
            'numeric',
            'min:0',
            // Cannot be less than paid_amount
            function ($attribute, $value, $fail) {
                if ($value < $this->invoice->paid_amount) {
                    $fail("لا يمكن تقليل القيمة أقل من المدفوع ({$this->invoice->paid_amount})");
                }
            },
        ],
    ];
}
```

### Edit Window Policy
```php
// InvoicePolicy.php
public function update(User $user, Invoice $invoice): bool
{
    $editDays = (int) Setting::get('edit_window_days', 1);
    $cutoffDate = now()->subDays($editDays)->startOfDay();
    
    return $invoice->date >= $cutoffDate;
}
```

---

## 🔧 Environment Configuration

### Local (.env)
```env
APP_ENV=local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=inventory_system
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost

GOOGLE_CLIENT_ID=xxx
GOOGLE_CLIENT_SECRET=xxx
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

### Production (.env)
```env
APP_ENV=production
DB_HOST=xxx.hostinger.com

SANCTUM_STATEFUL_DOMAINS=app.yoursite.com
SESSION_DOMAIN=.yoursite.com

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
```

---

## 📋 MVP Checklist

### Week 1
- [ ] Laravel project setup
- [ ] All migrations created
- [ ] All models with relationships
- [ ] Seeders (products, accounts, settings)

### Week 2
- [ ] All Observers implemented
- [ ] CollectionService (FIFO)
- [ ] FifoAllocatorService
- [ ] InvoiceNumberGenerator

### Week 3
- [ ] All API routes
- [ ] Request validation classes
- [ ] Policies (edit window)
- [ ] Sanctum + Google OAuth

### Week 4
- [ ] Reports (daily, shipment settlement)
- [ ] PDF export
- [ ] Excel export
- [ ] Testing

---

## 🔗 Related Files

- [Architecture_plan.md](../00-Core/Architecture_plan.md)
- [Database_Schema.md](../00-Core/Database_Schema.md)
- [Schema_Compliance_Matrix.md](Schema_Compliance_Matrix.md)
```
