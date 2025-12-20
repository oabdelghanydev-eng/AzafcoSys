<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CashboxController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health Check (Public - for monitoring)
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
    ]);
});

// Auth Routes (Public)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/google', [AuthController::class, 'googleRedirect']);
    Route::get('/google/callback', [AuthController::class, 'googleCallback']);
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']); // Alias as per Architecture_plan.md

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/activity', [DashboardController::class, 'recentActivity']);

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/statement', [ReportController::class, 'customerStatement']);

    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Products
    Route::apiResource('products', ProductController::class);

    // ═══════════════════════════════════════════════════════════════════
    // Operations requiring open daily report (working.day middleware)
    // ═══════════════════════════════════════════════════════════════════
    Route::middleware('working.day')->group(function () {
        // Invoices
        Route::apiResource('invoices', InvoiceController::class)->except(['update', 'destroy']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);

        // Collections
        Route::get('/collections/unpaid-invoices', [CollectionController::class, 'getUnpaidInvoices']);
        Route::apiResource('collections', CollectionController::class)->except(['update']);

        // Returns
        Route::apiResource('returns', ReturnController::class)->except(['update', 'destroy']);
        Route::post('/returns/{return}/cancel', [ReturnController::class, 'cancel']);

        // Expenses
        Route::apiResource('expenses', ExpenseController::class);
    });

    // Shipments (not tied to daily report - has own lifecycle)
    Route::get('/shipments/stock', [ShipmentController::class, 'stock']);
    Route::apiResource('shipments', ShipmentController::class);
    Route::post('/shipments/{shipment}/close', [ShipmentController::class, 'close']);
    Route::post('/shipments/{shipment}/settle', [ShipmentController::class, 'settle']);
    Route::post('/shipments/{shipment}/unsettle', [ShipmentController::class, 'unsettle']);
    Route::get('/shipments/{shipment}/settlement-report', [ShipmentController::class, 'settlementReport']);

    // Accounts & Treasury
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::get('/accounts/summary', [AccountController::class, 'summary']);
    Route::get('/accounts/{account}', [AccountController::class, 'show']);
    Route::get('/accounts/{account}/transactions', [AccountController::class, 'transactions']);

    // Cashbox Operations
    Route::get('/cashbox', [CashboxController::class, 'index']);
    Route::get('/cashbox/transactions', [CashboxController::class, 'transactions']);
    Route::post('/cashbox/deposit', [CashboxController::class, 'deposit']);
    Route::post('/cashbox/withdraw', [CashboxController::class, 'withdraw']);

    // Bank Operations
    Route::get('/bank', [BankController::class, 'index']);
    Route::get('/bank/transactions', [BankController::class, 'transactions']);
    Route::post('/bank/deposit', [BankController::class, 'deposit']);
    Route::post('/bank/withdraw', [BankController::class, 'withdraw']);

    // Transfers
    Route::apiResource('transfers', TransferController::class)->only(['index', 'store', 'show']);

    // Daily Reports (Working Day Session)
    Route::get('/daily/available', [DailyReportController::class, 'available']);
    Route::get('/daily/current', [DailyReportController::class, 'current']);
    Route::post('/daily/open', [DailyReportController::class, 'open']);
    Route::post('/daily/close', [DailyReportController::class, 'close']);
    Route::get('/daily/{date}', [DailyReportController::class, 'show']);
    Route::post('/daily/{date}/reopen', [DailyReportController::class, 'reopen']);

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/daily/{date}', [ReportController::class, 'daily']);
        Route::get('/daily/{date}/pdf', [ReportController::class, 'dailyPdf']);
        Route::get('/shipment/{shipment}', [ReportController::class, 'shipmentSettlement']);
        Route::get('/shipment/{shipment}/settlement/pdf', [ReportController::class, 'settlementPdf']);
        Route::get('/customer/{customer}', [ReportController::class, 'customerStatement']);
        Route::get('/supplier/{supplier}', [ReportController::class, 'supplierStatement']);
    });

    // Settings
    Route::get('/settings', [SettingController::class, 'index']);
    Route::get('/settings/{key}', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);

    // Users Management
    Route::get('/permissions', [UserController::class, 'permissions']);
    Route::apiResource('users', UserController::class);
    Route::post('/users/{user}/lock', [UserController::class, 'lock']);
    Route::post('/users/{user}/unlock', [UserController::class, 'unlock']);
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions']);
    Route::put('/users/{user}/password', [UserController::class, 'updatePassword']);

    // Audit Logs (Admin only)
    Route::prefix('audit')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);
        Route::get('/trail', [\App\Http\Controllers\Api\AuditLogController::class, 'trail']);
    });

    // Alerts (AI Smart Rules)
    Route::prefix('alerts')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AlertController::class, 'index']);
        Route::get('/summary', [\App\Http\Controllers\Api\AlertController::class, 'summary']);
        Route::post('/run-detection', [\App\Http\Controllers\Api\AlertController::class, 'runDetection']);
        Route::post('/{alert}/read', [\App\Http\Controllers\Api\AlertController::class, 'markAsRead']);
        Route::post('/{alert}/resolve', [\App\Http\Controllers\Api\AlertController::class, 'resolve']);
        Route::delete('/{alert}', [\App\Http\Controllers\Api\AlertController::class, 'destroy']);
    });

    // Credit/Debit Notes (Price Adjustments)
    Route::prefix('credit-notes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\CreditNoteController::class, 'index']);
        Route::post('/credit', [\App\Http\Controllers\Api\CreditNoteController::class, 'storeCredit']);
        Route::post('/debit', [\App\Http\Controllers\Api\CreditNoteController::class, 'storeDebit']);
        Route::get('/{creditNote}', [\App\Http\Controllers\Api\CreditNoteController::class, 'show']);
        Route::post('/{creditNote}/cancel', [\App\Http\Controllers\Api\CreditNoteController::class, 'cancel']);
        Route::get('/customer/{customer}', [\App\Http\Controllers\Api\CreditNoteController::class, 'customerNotes']);
    });

    // Price Adjustment for Invoices
    Route::post('/invoices/{invoice}/price-adjustment', [\App\Http\Controllers\Api\CreditNoteController::class, 'storePriceAdjustment']);
});

