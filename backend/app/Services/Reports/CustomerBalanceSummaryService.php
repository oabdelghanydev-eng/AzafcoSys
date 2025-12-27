<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Services\BaseService;

/**
 * CustomerBalanceSummaryService
 * 
 * ملخص أرصدة العملاء
 * 
 * @package App\Services\Reports
 */
class CustomerBalanceSummaryService extends BaseService
{
    /**
     * Generate customer balance summary report.
     *
     * @return array Report data
     */
    public function generate(): array
    {
        $customers = Customer::where('is_active', true)
            ->orderByDesc('balance')
            ->get();

        $customersWithDebt = $customers->where('balance', '>', 0);
        $customersWithCredit = $customers->where('balance', '<', 0);

        return [
            'as_of_date' => now()->format('Y-m-d'),
            'customers' => $customers->map(fn($c) => [
                'customer_id' => $c->id,
                'customer_code' => $c->code,
                'customer_name' => $c->name,
                'balance' => (float) $c->balance,
                'opening_balance' => (float) $c->opening_balance,
                'balance_type' => $c->balance > 0 ? 'debtor' : ($c->balance < 0 ? 'creditor' : 'settled'),
            ]),
            'totals' => [
                'total_debtors' => $customersWithDebt->sum('balance'),
                'total_creditors' => abs($customersWithCredit->sum('balance')),
                'net_balance' => $customers->sum('balance'),
            ],
            'summary' => [
                'total_customers' => $customers->count(),
                'debtors_count' => $customersWithDebt->count(),
                'creditors_count' => $customersWithCredit->count(),
                'settled_count' => $customers->where('balance', 0)->count(),
            ],
        ];
    }

    protected function getServiceName(): string
    {
        return 'CustomerBalanceSummaryService';
    }
}
