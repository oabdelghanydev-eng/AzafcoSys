<?php

namespace App\Services\Reports;

use App\Models\Shipment;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * WastageReportService
 * 
 * تقرير الهالك
 * 
 * يحسب الفرق بين الوزن الوارد والوزن المباع (الهالك)
 * 
 * @package App\Services\Reports
 */
class WastageReportService extends BaseService
{
    /**
     * Generate wastage report for settled shipments.
     *
     * @param string|null $dateFrom Start date (settled_at)
     * @param string|null $dateTo End date (settled_at)
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $shipments = $this->getSettledShipments($dateFrom, $dateTo);

        $wastageByShipment = $shipments->map(fn($s) => $this->calculateShipmentWastage($s));

        $totalWeightIn = $wastageByShipment->sum('weight_in');
        $totalWeightOut = $wastageByShipment->sum('weight_sold');
        $totalWastage = $wastageByShipment->sum('wastage');

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'shipments' => $wastageByShipment,
            'by_product' => $this->getWastageByProduct($dateFrom, $dateTo),
            'summary' => [
                'total_shipments' => $shipments->count(),
                'total_weight_in' => $totalWeightIn,
                'total_weight_sold' => $totalWeightOut,
                'total_wastage' => $totalWastage,
                'wastage_percentage' => $totalWeightIn > 0
                    ? round(($totalWastage / $totalWeightIn) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Get settled shipments in period.
     */
    protected function getSettledShipments(?string $dateFrom, ?string $dateTo)
    {
        return Shipment::where('status', 'settled')
            ->with(['supplier', 'items.product'])
            ->when($dateFrom, fn($q) => $q->whereDate('settled_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('settled_at', '<=', $dateTo))
            ->orderBy('settled_at')
            ->get();
    }

    /**
     * Calculate wastage for a single shipment.
     */
    protected function calculateShipmentWastage(Shipment $shipment): array
    {
        $weightIn = 0;
        $weightSold = 0;

        foreach ($shipment->items as $item) {
            // الوزن الوارد = (الكراتين الأصلية + المرحل للداخل) × وزن الوحدة
            $inCartons = $item->cartons + $item->carryover_in_cartons;
            $weightIn += $inCartons * $item->weight_per_unit;

            // الوزن المباع
            $weightSold += $item->sold_cartons * $item->weight_per_unit;
        }

        // الوزن المرحل للخارج (لا يُحسب كهالك)
        $weightCarryoutOut = $shipment->items->sum(fn($i) => $i->carryover_out_cartons * $i->weight_per_unit);

        // الوزن الفعلي = الوارد - المرحل للخارج
        $effectiveWeightIn = $weightIn - $weightCarryoutOut;

        // الهالك = الفعلي - المباع
        $wastage = $effectiveWeightIn - $weightSold;

        return [
            'shipment_id' => $shipment->id,
            'shipment_number' => $shipment->number,
            'supplier_name' => $shipment->supplier->name,
            'settled_at' => $shipment->settled_at?->format('Y-m-d'),
            'weight_in' => $effectiveWeightIn,
            'weight_sold' => $weightSold,
            'wastage' => $wastage,
            'wastage_percentage' => $effectiveWeightIn > 0
                ? round(($wastage / $effectiveWeightIn) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get wastage grouped by product.
     */
    protected function getWastageByProduct(?string $dateFrom, ?string $dateTo)
    {
        return DB::table('shipment_items')
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->where('shipments.status', 'settled')
            ->when($dateFrom, fn($q) => $q->whereDate('shipments.settled_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('shipments.settled_at', '<=', $dateTo))
            ->selectRaw('
                products.id as product_id,
                products.name_ar as product_name,
                SUM((shipment_items.cartons + shipment_items.carryover_in_cartons - shipment_items.carryover_out_cartons) * shipment_items.weight_per_unit) as weight_in,
                SUM(shipment_items.sold_cartons * shipment_items.weight_per_unit) as weight_sold,
                SUM((shipment_items.cartons + shipment_items.carryover_in_cartons - shipment_items.carryover_out_cartons - shipment_items.sold_cartons) * shipment_items.weight_per_unit) as wastage
            ')
            ->groupBy('products.id', 'products.name_ar')
            ->orderByDesc('wastage')
            ->get()
            ->map(fn($p) => [
                'product_id' => $p->product_id,
                'product_name' => $p->product_name,
                'weight_in' => (float) $p->weight_in,
                'weight_sold' => (float) $p->weight_sold,
                'wastage' => (float) $p->wastage,
                'wastage_percentage' => $p->weight_in > 0
                    ? round(($p->wastage / $p->weight_in) * 100, 2)
                    : 0,
            ]);
    }

    protected function getServiceName(): string
    {
        return 'WastageReportService';
    }
}
