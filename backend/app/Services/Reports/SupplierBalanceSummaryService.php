<?php

namespace App\Services\Reports;

use App\Models\Supplier;
use App\Services\BaseService;

/**
 * SupplierBalanceSummaryService
 * 
 * ملخص أرصدة الموردين
 * 
 * @package App\Services\Reports
 */
class SupplierBalanceSummaryService extends BaseService
{
    /**
     * Generate supplier balance summary report.
     *
     * @return array Report data
     */
    public function generate(): array
    {
        $suppliers = Supplier::where('is_active', true)
            ->orderByDesc('balance')
            ->get();

        $suppliersOwed = $suppliers->where('balance', '>', 0); // نحن مدينين لهم
        $suppliersOwe = $suppliers->where('balance', '<', 0);   // هم مدينين لنا

        return [
            'as_of_date' => now()->format('Y-m-d'),
            'suppliers' => $suppliers->map(fn($s) => [
                'supplier_id' => $s->id,
                'supplier_code' => $s->code,
                'supplier_name' => $s->name,
                'balance' => (float) $s->balance,
                'opening_balance' => (float) $s->opening_balance,
                'balance_type' => $s->balance > 0 ? 'we_owe_supplier' : ($s->balance < 0 ? 'supplier_owes_us' : 'settled'),
            ]),
            'totals' => [
                'we_owe_suppliers' => $suppliersOwed->sum('balance'),
                'suppliers_owe_us' => abs($suppliersOwe->sum('balance')),
                'net_balance' => $suppliers->sum('balance'),
            ],
            'summary' => [
                'total_suppliers' => $suppliers->count(),
                'suppliers_we_owe' => $suppliersOwed->count(),
                'suppliers_owe_us' => $suppliersOwe->count(),
                'settled_suppliers' => $suppliers->where('balance', 0)->count(),
            ],
        ];
    }

    protected function getServiceName(): string
    {
        return 'SupplierBalanceSummaryService';
    }
}
