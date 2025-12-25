<?php

namespace App\Services\Reports;

use App\Models\Shipment;
use App\Models\Supplier;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * SupplierPerformanceService
 * 
 * تقرير أداء الموردين
 * 
 * يحلل أداء كل مورد من حيث:
 * - عدد الشحنات
 * - إجمالي المبيعات
 * - نسبة الهالك
 * - متوسط مدة البقاء
 * 
 * @package App\Services\Reports
 */
class SupplierPerformanceService extends BaseService
{
    /**
     * Generate supplier performance report.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $suppliers = Supplier::where('is_active', true)->get();

        $performanceData = $suppliers->map(fn($s) => $this->calculateSupplierPerformance($s, $dateFrom, $dateTo))
            ->filter(fn($p) => $p['shipments_count'] > 0) // فقط الموردين الذين لديهم شحنات
            ->sortByDesc('total_sales')
            ->values();

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'suppliers' => $performanceData,
            'summary' => [
                'total_suppliers' => $performanceData->count(),
                'total_shipments' => $performanceData->sum('shipments_count'),
                'total_sales' => $performanceData->sum('total_sales'),
                'avg_wastage_rate' => $performanceData->avg('wastage_percentage') ?? 0,
            ],
        ];
    }

    /**
     * Calculate performance metrics for a single supplier.
     */
    protected function calculateSupplierPerformance(Supplier $supplier, ?string $dateFrom, ?string $dateTo): array
    {
        // الشحنات المُصفاة للمورد
        $shipments = Shipment::where('supplier_id', $supplier->id)
            ->where('status', 'settled')
            ->when($dateFrom, fn($q) => $q->whereDate('settled_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('settled_at', '<=', $dateTo))
            ->with('items')
            ->get();

        if ($shipments->isEmpty()) {
            return [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'shipments_count' => 0,
                'total_sales' => 0,
                'total_weight_in' => 0,
                'total_weight_sold' => 0,
                'wastage_percentage' => 0,
                'avg_days_to_settle' => 0,
            ];
        }

        $totalSales = $shipments->sum('total_sales');
        $totalWeightIn = 0;
        $totalWeightSold = 0;
        $totalDays = 0;

        foreach ($shipments as $shipment) {
            // حساب الوزن
            foreach ($shipment->items as $item) {
                $inCartons = $item->cartons + $item->carryover_in_cartons - $item->carryover_out_cartons;
                $totalWeightIn += $inCartons * $item->weight_per_unit;
                $totalWeightSold += $item->sold_cartons * $item->weight_per_unit;
            }

            // حساب مدة البقاء
            if ($shipment->settled_at && $shipment->date) {
                $totalDays += $shipment->date->diffInDays($shipment->settled_at);
            }
        }

        $wastage = $totalWeightIn - $totalWeightSold;

        return [
            'supplier_id' => $supplier->id,
            'supplier_code' => $supplier->code,
            'supplier_name' => $supplier->name,
            'shipments_count' => $shipments->count(),
            'total_sales' => $totalSales,
            'total_weight_in' => $totalWeightIn,
            'total_weight_sold' => $totalWeightSold,
            'wastage' => $wastage,
            'wastage_percentage' => $totalWeightIn > 0
                ? round(($wastage / $totalWeightIn) * 100, 2)
                : 0,
            'avg_days_to_settle' => $shipments->count() > 0
                ? round($totalDays / $shipments->count(), 1)
                : 0,
        ];
    }

    protected function getServiceName(): string
    {
        return 'SupplierPerformanceService';
    }
}
