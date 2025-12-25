<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Supplier;
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

    /**
     * Get comprehensive financial summary
     * Shows company profit, supplier dues, and expense breakdown
     */
    public function financialSummary(): JsonResponse
    {
        $commissionRate = (float) (config('settings.company_commission_rate', 6)) / 100;

        // Get all settled shipments data
        $settledShipments = Shipment::where('status', 'settled')->get();

        // Total sales from all settled shipments
        $totalSales = $settledShipments->sum('total_sales');

        // Commission earned (6% of sales)
        $totalCommission = $totalSales * $commissionRate;

        // Company expenses
        $companyExpenses = Expense::where('type', 'company')->sum('amount');

        // Company net profit = Commission - Company Expenses
        $companyNetProfit = $totalCommission - $companyExpenses;

        // Supplier expenses (paid on behalf)
        $supplierExpenses = Expense::where('type', 'supplier')->sum('amount');

        // Supplier payments made
        $supplierPayments = Expense::where('type', 'supplier_payment')->sum('amount');

        // Net due to suppliers = Sales - Commission - Supplier Expenses - Payments
        $netDueToSuppliers = $totalSales - $totalCommission - $supplierExpenses - $supplierPayments;

        // Per supplier breakdown
        $supplierBreakdown = Supplier::with([
            'shipments' => function ($q) {
                $q->where('status', 'settled');
            }
        ])->get()->map(function ($supplier) use ($commissionRate) {
            $sales = $supplier->shipments->sum('total_sales');
            $commission = $sales * $commissionRate;
            $expenses = Expense::where('type', 'supplier')->where('supplier_id', $supplier->id)->sum('amount');
            $payments = Expense::where('type', 'supplier_payment')->where('supplier_id', $supplier->id)->sum('amount');

            return [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'total_sales' => (float) $sales,
                'commission' => (float) $commission,
                'expenses' => (float) $expenses,
                'payments' => (float) $payments,
                'net_due' => (float) ($sales - $commission - $expenses - $payments),
                'stored_balance' => (float) $supplier->balance,
            ];
        });

        return $this->success([
            // Company Summary
            'company' => [
                'total_commission' => (float) $totalCommission,
                'total_expenses' => (float) $companyExpenses,
                'net_profit' => (float) $companyNetProfit,
            ],

            // Supplier Summary
            'suppliers' => [
                'total_sales' => (float) $totalSales,
                'total_commission_deducted' => (float) $totalCommission,
                'total_expenses_on_behalf' => (float) $supplierExpenses,
                'total_payments_made' => (float) $supplierPayments,
                'net_due_to_all' => (float) $netDueToSuppliers,
            ],

            // Detailed breakdown per supplier
            'supplier_breakdown' => $supplierBreakdown,

            // Commission rate used
            'commission_rate' => $commissionRate * 100 . '%',
        ]);
    }
}
