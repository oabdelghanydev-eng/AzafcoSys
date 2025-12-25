<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Transfer;
use App\Services\BaseService;

/**
 * CashFlowReportService
 * 
 * تقرير التدفق النقدي
 * 
 * صافي التدفق = الداخل (تحصيلات) - الخارج (مصروفات + مدفوعات)
 * 
 * @package App\Services\Reports
 */
class CashFlowReportService extends BaseService
{
    /**
     * Generate cash flow report for a period.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        // التدفقات الداخلة (التحصيلات)
        $inflows = $this->calculateInflows($dateFrom, $dateTo);

        // التدفقات الخارجة (المصروفات + المدفوعات)
        $outflows = $this->calculateOutflows($dateFrom, $dateTo);

        // صافي التدفق النقدي
        $netCashFlow = $inflows['total'] - $outflows['total'];

        // أرصدة الحسابات الحالية
        $accountBalances = $this->getAccountBalances();

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'inflows' => $inflows,
            'outflows' => $outflows,
            'net_cash_flow' => $netCashFlow,
            'account_balances' => $accountBalances,
            'summary' => [
                'total_inflows' => $inflows['total'],
                'total_outflows' => $outflows['total'],
                'net_flow' => $netCashFlow,
                'cashbox_balance' => $accountBalances['cashbox'],
                'bank_balance' => $accountBalances['bank'],
                'total_liquidity' => $accountBalances['cashbox'] + $accountBalances['bank'],
            ],
        ];
    }

    /**
     * Calculate cash inflows (Collections).
     * التدفقات الداخلة = التحصيلات من العملاء
     */
    protected function calculateInflows(?string $dateFrom, ?string $dateTo): array
    {
        $collectionsQuery = Collection::query()
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo));

        $collections = $collectionsQuery->get();

        $byCash = $collections->where('payment_method', 'cash')->sum('amount');
        $byBank = $collections->where('payment_method', 'bank')->sum('amount');

        // تفاصيل يومية
        $byDate = $collections->groupBy(fn($c) => $c->date->format('Y-m-d'))
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'count' => $items->count(),
                    'amount' => $items->sum('amount'),
                ];
            })->values();

        return [
            'by_payment_method' => [
                'cash' => $byCash,
                'bank' => $byBank,
            ],
            'by_date' => $byDate,
            'count' => $collections->count(),
            'total' => $byCash + $byBank,
        ];
    }

    /**
     * Calculate cash outflows (Expenses + Payments).
     * التدفقات الخارجة = المصروفات + المدفوعات للموردين
     */
    protected function calculateOutflows(?string $dateFrom, ?string $dateTo): array
    {
        $expensesQuery = Expense::query()
            ->when($dateFrom, fn($q) => $q->whereDate('date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('date', '<=', $dateTo));

        $expenses = $expensesQuery->get();

        // تصنيف حسب النوع
        $companyExpenses = $expenses->where('type', 'company')->sum('amount');
        $supplierExpenses = $expenses->where('type', 'supplier')->sum('amount');
        $supplierPayments = $expenses->where('type', 'supplier_payment')->sum('amount');

        // تصنيف حسب طريقة الدفع
        $byCash = $expenses->where('payment_method', 'cash')->sum('amount');
        $byBank = $expenses->where('payment_method', 'bank')->sum('amount');

        // تفاصيل يومية
        $byDate = $expenses->groupBy(fn($e) => $e->date->format('Y-m-d'))
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'count' => $items->count(),
                    'amount' => $items->sum('amount'),
                ];
            })->values();

        return [
            'by_type' => [
                'company_expenses' => $companyExpenses,
                'supplier_expenses' => $supplierExpenses,
                'supplier_payments' => $supplierPayments,
            ],
            'by_payment_method' => [
                'cash' => $byCash,
                'bank' => $byBank,
            ],
            'by_date' => $byDate,
            'count' => $expenses->count(),
            'total' => $expenses->sum('amount'),
        ];
    }

    /**
     * Get current account balances.
     */
    protected function getAccountBalances(): array
    {
        $cashbox = Account::where('type', 'cashbox')->first();
        $bank = Account::where('type', 'bank')->first();

        return [
            'cashbox' => (float) ($cashbox->balance ?? 0),
            'bank' => (float) ($bank->balance ?? 0),
        ];
    }

    protected function getServiceName(): string
    {
        return 'CashFlowReportService';
    }
}
