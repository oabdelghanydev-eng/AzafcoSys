<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CustomerAgingService
 * 
 * تقرير أعمار الديون (Aging Report)
 * 
 * يصنف ديون العملاء حسب عمر الفاتورة:
 * - 0-30 يوم (جاري)
 * - 31-60 يوم (متأخر)
 * - 61-90 يوم (متأخر جداً)
 * - 90+ يوم (متعثر)
 * 
 * @package App\Services\Reports
 */
class CustomerAgingService extends BaseService
{
    /**
     * Generate customer aging report.
     *
     * @return array Report data
     */
    public function generate(): array
    {
        $customers = Customer::where('balance', '>', 0)
            ->orderByDesc('balance')
            ->get();

        $agingData = $customers->map(function ($customer) {
            return $this->calculateCustomerAging($customer);
        });

        // حساب الإجماليات
        $totals = [
            'current' => $agingData->sum('aging.current'),
            'days_31_60' => $agingData->sum('aging.days_31_60'),
            'days_61_90' => $agingData->sum('aging.days_61_90'),
            'over_90' => $agingData->sum('aging.over_90'),
            'total' => $agingData->sum('total_balance'),
        ];

        return [
            'as_of_date' => now()->format('Y-m-d'),
            'customers' => $agingData,
            'totals' => $totals,
            'summary' => [
                'total_customers' => $customers->count(),
                'total_debt' => $totals['total'],
                'current_percentage' => $totals['total'] > 0
                    ? round(($totals['current'] / $totals['total']) * 100, 1)
                    : 0,
                'overdue_percentage' => $totals['total'] > 0
                    ? round((($totals['days_31_60'] + $totals['days_61_90'] + $totals['over_90']) / $totals['total']) * 100, 1)
                    : 0,
            ],
        ];
    }

    /**
     * Calculate aging for a single customer.
     */
    protected function calculateCustomerAging(Customer $customer): array
    {
        $today = Carbon::today();

        // الفواتير غير المسددة
        $unpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->get();

        $aging = [
            'current' => 0,      // 0-30
            'days_31_60' => 0,   // 31-60
            'days_61_90' => 0,   // 61-90
            'over_90' => 0,      // 90+
        ];

        foreach ($unpaidInvoices as $invoice) {
            $daysOld = $invoice->date->diffInDays($today);
            $balance = (float) $invoice->balance;

            if ($daysOld <= 30) {
                $aging['current'] += $balance;
            } elseif ($daysOld <= 60) {
                $aging['days_31_60'] += $balance;
            } elseif ($daysOld <= 90) {
                $aging['days_61_90'] += $balance;
            } else {
                $aging['over_90'] += $balance;
            }
        }

        return [
            'customer_id' => $customer->id,
            'customer_code' => $customer->code,
            'customer_name' => $customer->name,
            'total_balance' => (float) $customer->balance,
            'invoices_count' => $unpaidInvoices->count(),
            'aging' => $aging,
            'oldest_invoice_days' => $unpaidInvoices->max(fn($i) => $i->date->diffInDays($today)) ?? 0,
        ];
    }

    protected function getServiceName(): string
    {
        return 'CustomerAgingService';
    }
}
