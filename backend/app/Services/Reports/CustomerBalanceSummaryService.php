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
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'balance' => (float) $c->balance,
                'opening_balance' => (float) $c->opening_balance,
                'status' => $c->balance > 0 ? 'debtor' : ($c->balance < 0 ? 'creditor' : 'settled'),
            ]),
            'summary' => [
                'total_customers' => $customers->count(),
                'customers_with_debt' => $customersWithDebt->count(),
                'customers_with_credit' => $customersWithCredit->count(),
                'customers_settled' => $customers->where('balance', 0)->count(),
                'total_market_debt' => $customersWithDebt->sum('balance'),
                'total_credit' => abs($customersWithCredit->sum('balance')),
                'net_market_balance' => $customers->sum('balance'),
            ],
        ];
    }

    protected function getServiceName(): string
    {
        return 'CustomerBalanceSummaryService';
    }
}
