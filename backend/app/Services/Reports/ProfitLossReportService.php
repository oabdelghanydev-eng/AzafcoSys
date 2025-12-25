<?php

namespace App\Services\Reports;

use App\Models\Collection;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * ProfitLossReportService
 * 
 * تقرير الأرباح والخسائر
 * 
 * الربح = إيرادات العمولة - مصروفات الشركة
 * 
 * @package App\Services\Reports
 */
class ProfitLossReportService extends BaseService
{
    /**
     * Generate profit & loss report for a period.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        // الإيرادات
        $revenue = $this->calculateRevenue($dateFrom, $dateTo);

        // المصروفات
        $expenses = $this->calculateExpenses($dateFrom, $dateTo);

        // صافي الربح
        $netProfit = $revenue['total'] - $expenses['total'];

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'summary' => [
                'total_revenue' => $revenue['total'],
                'total_expenses' => $expenses['total'],
                'net_profit' => $netProfit,
                'profit_margin' => $revenue['total'] > 0
                    ? round(($netProfit / $revenue['total']) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Calculate revenue (Commission from sales).
     * الإيرادات = العمولة من المبيعات
     */
    protected function calculateRevenue(?string $dateFrom, ?string $dateTo): array
    {
        // العمولة من الشحنات المُصفاة
        $commissionQuery = Shipment::where('status', 'settled')
            ->when($dateFrom, fn($q) => $q->whereDate('settled_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('settled_at', '<=', $dateTo));

        $settledShipments = $commissionQuery->get();

        $totalSales = $settledShipments->sum('total_sales');
        $commissionRate = config('settings.company_commission_rate', 6) / 100;
        $totalCommission = $totalSales * $commissionRate;

        // مبيعات نقدية مباشرة (لو فيه)
        $directCashSales = Invoice::where('payment_method', 'cash')
            ->where('status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo))
            ->sum('total');

        return [
            'commission' => [
                'total_sales' => $totalSales,
                'commission_rate' => $commissionRate * 100,
                'amount' => $totalCommission,
            ],
            'direct_sales' => [
                'cash_sales' => $directCashSales,
            ],
            'total' => $totalCommission, // العمولة هي الإيراد الرئيسي
        ];
    }

    /**
     * Calculate expenses (Company expenses only).
     * المصروفات = مصروفات الشركة فقط (مش مصروفات الموردين)
     */
    protected function calculateExpenses(?string $dateFrom, ?string $dateTo): array
    {
        $expensesQuery = Expense::where('type', 'company')
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo));

        $expenses = $expensesQuery->get();

        // تصنيف المصروفات
        $byCategory = $expenses->groupBy('category')->map(function ($items, $category) {
            return [
                'category' => $category ?: 'عام',
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
            ];
        })->values();

        // بطريقة الدفع
        $byPaymentMethod = [
            'cash' => $expenses->where('payment_method', 'cash')->sum('amount'),
            'bank' => $expenses->where('payment_method', 'bank')->sum('amount'),
        ];

        return [
            'by_category' => $byCategory,
            'by_payment_method' => $byPaymentMethod,
            'total' => $expenses->sum('amount'),
            'count' => $expenses->count(),
        ];
    }

    protected function getServiceName(): string
    {
        return 'ProfitLossReportService';
    }
}
