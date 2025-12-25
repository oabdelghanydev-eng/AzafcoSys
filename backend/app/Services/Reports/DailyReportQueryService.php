<?php

namespace App\Services\Reports;

use App\Models\Collection;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\BaseService;

/**
 * DailyReportQueryService
 * 
 * Handles daily report data queries (separate from DailyReportService which handles open/close).
 * 
 * @package App\Services\Reports
 */
class DailyReportQueryService extends BaseService
{
    /**
     * Get daily summary report data.
     *
     * @param string $date Date in Y-m-d format
     * @return array Report data
     */
    public function getDailySummary(string $date): array
    {
        $sales = $this->getSalesData($date);
        $collections = $this->getCollectionsData($date);
        $expenses = $this->getExpensesData($date);

        // Cash balance calculation
        $cashIn = (float) $collections['cash'];
        $cashOut = (float) $expenses['cash'];
        $netCash = $cashIn - $cashOut;

        return [
            'date' => $date,
            'sales' => $sales,
            'collections' => $collections,
            'expenses' => $expenses,
            'net' => [
                'cash' => $netCash,
                'sales_vs_collections' => $sales['total'] - $collections['total'],
            ],
        ];
    }

    /**
     * Get sales data for a date.
     */
    protected function getSalesData(string $date): array
    {
        $data = Invoice::where('date', $date)
            ->where('status', 'active')
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(discount), 0) as total_discount
            ')
            ->first();

        return [
            'count' => (int) $data->count,
            'total' => (float) $data->total,
            'discount' => (float) $data->total_discount,
        ];
    }

    /**
     * Get collections data for a date.
     */
    protected function getCollectionsData(string $date): array
    {
        $data = Collection::where('date', $date)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN payment_method = "bank" THEN amount ELSE 0 END), 0) as bank_total
            ')
            ->first();

        return [
            'count' => (int) $data->count,
            'total' => (float) $data->total,
            'cash' => (float) $data->cash_total,
            'bank' => (float) $data->bank_total,
        ];
    }

    /**
     * Get expenses data for a date.
     */
    protected function getExpensesData(string $date): array
    {
        $data = Expense::where('date', $date)
            ->selectRaw('
                COUNT(*) as count,
                COALESCE(SUM(amount), 0) as total,
                COALESCE(SUM(CASE WHEN payment_method = "cash" THEN amount ELSE 0 END), 0) as cash_total,
                COALESCE(SUM(CASE WHEN payment_method = "bank" THEN amount ELSE 0 END), 0) as bank_total,
                COALESCE(SUM(CASE WHEN type = "supplier" THEN amount ELSE 0 END), 0) as supplier_total,
                COALESCE(SUM(CASE WHEN type = "company" THEN amount ELSE 0 END), 0) as company_total
            ')
            ->first();

        return [
            'count' => (int) $data->count,
            'total' => (float) $data->total,
            'cash' => (float) $data->cash_total,
            'bank' => (float) $data->bank_total,
            'supplier' => (float) $data->supplier_total,
            'company' => (float) $data->company_total,
        ];
    }

    protected function getServiceName(): string
    {
        return 'DailyReportQueryService';
    }
}
