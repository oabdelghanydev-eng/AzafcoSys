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
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'balance' => (float) $s->balance,
                'opening_balance' => (float) $s->opening_balance,
                'status' => $s->balance > 0 ? 'owed' : ($s->balance < 0 ? 'owes' : 'settled'),
            ]),
            'summary' => [
                'total_suppliers' => $suppliers->count(),
                'suppliers_owed' => $suppliersOwed->count(),        // نحن ندين لهم
                'suppliers_owe' => $suppliersOwe->count(),          // هم يدينون لنا
                'suppliers_settled' => $suppliers->where('balance', 0)->count(),
                'total_we_owe' => $suppliersOwed->sum('balance'),   // إجمالي ما نحن مدينين
                'total_they_owe' => abs($suppliersOwe->sum('balance')), // إجمالي ما هم مدينين
                'net_balance' => $suppliers->sum('balance'),
            ],
        ];
    }

    protected function getServiceName(): string
    {
        return 'SupplierBalanceSummaryService';
    }
}
