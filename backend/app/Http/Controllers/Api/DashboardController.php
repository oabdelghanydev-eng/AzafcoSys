<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Shipment;
use App\Models\Invoice;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * @tags Dashboard
 */
class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get dashboard statistics
     */
    public function index(): JsonResponse
    {
        $today = now()->toDateString();

        $stats = [
            // Counts
            'customers_count' => Customer::count(),
            'suppliers_count' => Supplier::count(),
            'products_count' => Product::where('is_active', true)->count(),

            // Balances
            'total_receivables' => (float) Customer::where('balance', '>', 0)->sum('balance'),
            'total_payables' => (float) Supplier::where('balance', '>', 0)->sum('balance'),

            // Shipments
            'open_shipments' => Shipment::where('status', 'open')->count(),
            'closed_shipments' => Shipment::where('status', 'closed')->count(),

            // Today's totals
            'today_sales' => (float) Invoice::where('date', $today)
                ->where('status', 'active')
                ->sum('total'),
            'today_sales_count' => Invoice::where('date', $today)
                ->where('status', 'active')
                ->count(),
            'today_collections' => (float) Collection::where('date', $today)->sum('amount'),
            'today_collections_count' => Collection::where('date', $today)->count(),
            'today_expenses' => (float) Expense::where('date', $today)->sum('amount'),
            'today_expenses_count' => Expense::where('date', $today)->count(),

            // Net cash for today
            'today_net_cash' => (float) Collection::where('date', $today)
                ->where('payment_method', 'cash')
                ->sum('amount')
                - (float) Expense::where('date', $today)
                    ->where('payment_method', 'cash')
                    ->sum('amount'),

            // Top customers by balance
            'top_debtors' => Customer::where('balance', '>', 0)
                ->orderByDesc('balance')
                ->take(5)
                ->get(['id', 'name', 'code', 'balance']),
        ];

        return $this->success($stats);
    }

    /**
     * Get recent activity
     */
    public function recentActivity(): JsonResponse
    {
        $recentInvoices = Invoice::with('customer:id,name')
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['id', 'invoice_number', 'customer_id', 'total', 'date', 'status', 'created_at']);

        $recentCollections = Collection::with('customer:id,name')
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['id', 'receipt_number', 'customer_id', 'amount', 'date', 'payment_method', 'created_at']);

        $recentExpenses = Expense::with('supplier:id,name')
            ->orderByDesc('created_at')
            ->take(5)
            ->get(['id', 'expense_number', 'supplier_id', 'amount', 'date', 'type', 'created_at']);

        return $this->success([
            'invoices' => $recentInvoices,
            'collections' => $recentCollections,
            'expenses' => $recentExpenses,
        ]);
    }
}
