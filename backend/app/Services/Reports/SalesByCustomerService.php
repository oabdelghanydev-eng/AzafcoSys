<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * SalesByCustomerService
 * 
 * تقرير المبيعات حسب العميل
 * 
 * @package App\Services\Reports
 */
class SalesByCustomerService extends BaseService
{
    /**
     * Generate sales by customer report for a period.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $salesData = $this->getSalesByCustomer($dateFrom, $dateTo);

        $totalRevenue = $salesData->sum('total_sales');
        $totalInvoices = $salesData->sum('invoices_count');

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'customers' => $salesData,
            'summary' => [
                'total_customers' => $salesData->count(),
                'total_invoices' => $totalInvoices,
                'total_revenue' => $totalRevenue,
                'avg_per_customer' => $salesData->count() > 0
                    ? round($totalRevenue / $salesData->count(), 2)
                    : 0,
            ],
        ];
    }

    /**
     * Get sales grouped by customer.
     */
    protected function getSalesByCustomer(?string $dateFrom, ?string $dateTo)
    {
        return DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('invoices.date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('invoices.date', '<=', $dateTo))
            ->selectRaw('
                customers.id as customer_id,
                customers.code as customer_code,
                customers.name as customer_name,
                COUNT(invoices.id) as invoices_count,
                SUM(invoices.total) as total_sales,
                SUM(invoices.paid_amount) as total_paid,
                SUM(invoices.balance) as total_balance,
                AVG(invoices.total) as avg_invoice_value
            ')
            ->groupBy('customers.id', 'customers.code', 'customers.name')
            ->orderByDesc('total_sales')
            ->get();
    }

    protected function getServiceName(): string
    {
        return 'SalesByCustomerService';
    }
}
