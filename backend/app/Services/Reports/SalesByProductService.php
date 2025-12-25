<?php

namespace App\Services\Reports;

use App\Models\InvoiceItem;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * SalesByProductService
 * 
 * تقرير المبيعات حسب المنتج
 * 
 * @package App\Services\Reports
 */
class SalesByProductService extends BaseService
{
    /**
     * Generate sales by product report for a period.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $salesData = $this->getSalesByProduct($dateFrom, $dateTo);

        $totalQuantity = $salesData->sum('quantity');
        $totalWeight = $salesData->sum('weight');
        $totalRevenue = $salesData->sum('revenue');

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'products' => $salesData,
            'summary' => [
                'total_products' => $salesData->count(),
                'total_quantity' => $totalQuantity,
                'total_weight' => $totalWeight,
                'total_revenue' => $totalRevenue,
                'avg_price_per_kg' => $totalWeight > 0 ? round($totalRevenue / $totalWeight, 2) : 0,
            ],
        ];
    }

    /**
     * Get sales grouped by product.
     */
    protected function getSalesByProduct(?string $dateFrom, ?string $dateTo)
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->where('invoices.status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('invoices.date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('invoices.date', '<=', $dateTo))
            ->selectRaw('
                products.id as product_id,
                products.name_ar as product_name,
                products.name_en as product_name_en,
                SUM(invoice_items.quantity) as quantity,
                SUM(invoice_items.quantity * shipment_items.weight_per_unit) as weight,
                SUM(invoice_items.subtotal) as revenue,
                AVG(invoice_items.unit_price) as avg_unit_price,
                COUNT(DISTINCT invoices.id) as invoices_count
            ')
            ->groupBy('products.id', 'products.name_ar', 'products.name_en')
            ->orderByDesc('revenue')
            ->get();
    }

    protected function getServiceName(): string
    {
        return 'SalesByProductService';
    }
}
