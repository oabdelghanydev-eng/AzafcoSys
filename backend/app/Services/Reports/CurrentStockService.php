<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\ShipmentItem;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * CurrentStockService
 * 
 * تقرير المخزون الحالي
 * 
 * يعرض الكميات المتبقية من كل منتج في الشحنات المفتوحة
 * 
 * @package App\Services\Reports
 */
class CurrentStockService extends BaseService
{
    /**
     * Generate current stock report.
     *
     * @return array Report data
     */
    public function generate(): array
    {
        $stockData = $this->getStockByProduct();

        $totalCartons = $stockData->sum('total_cartons');
        $totalWeight = $stockData->sum('total_weight');

        return [
            'as_of_date' => now()->format('Y-m-d H:i'),
            'products' => $stockData,
            'summary' => [
                'total_products' => $stockData->count(),
                'total_cartons' => $totalCartons,
                'total_weight' => $totalWeight,
                'shipments_count' => $stockData->sum('shipments_count'),
            ],
        ];
    }

    /**
     * Get stock grouped by product from open/closed shipments.
     */
    protected function getStockByProduct()
    {
        return DB::table('shipment_items')
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->whereIn('shipments.status', ['open', 'closed'])
            ->selectRaw('
                products.id as product_id,
                products.name_ar as product_name,
                products.name_en as product_name_en,
                SUM(shipment_items.cartons + shipment_items.carryover_in_cartons - shipment_items.sold_cartons - shipment_items.carryover_out_cartons) as total_cartons,
                SUM((shipment_items.cartons + shipment_items.carryover_in_cartons - shipment_items.sold_cartons - shipment_items.carryover_out_cartons) * shipment_items.weight_per_unit) as total_weight,
                COUNT(DISTINCT shipments.id) as shipments_count,
                AVG(shipment_items.weight_per_unit) as avg_weight_per_unit
            ')
            ->groupBy('products.id', 'products.name_ar', 'products.name_en')
            ->having('total_cartons', '>', 0)
            ->orderByDesc('total_cartons')
            ->get();
    }

    /**
     * Get detailed stock with shipment breakdown.
     */
    public function getDetailedStock(): array
    {
        $items = ShipmentItem::with(['shipment.supplier', 'product'])
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->where(DB::raw('cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons'), '>', 0)
            ->get();

        return $items->map(fn($item) => [
            'product_id' => $item->product_id,
            'product_name' => $item->product->name_ar,
            'shipment_number' => $item->shipment->number,
            'supplier_name' => $item->shipment->supplier->name,
            'shipment_date' => $item->shipment->date->format('Y-m-d'),
            'remaining_cartons' => $item->remaining_cartons,
            'remaining_weight' => $item->remaining_cartons * $item->weight_per_unit,
            'weight_per_unit' => $item->weight_per_unit,
        ])->toArray();
    }

    protected function getServiceName(): string
    {
        return 'CurrentStockService';
    }
}
