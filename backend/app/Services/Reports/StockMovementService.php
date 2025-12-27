<?php

namespace App\Services\Reports;

use App\Models\Carryover;
use App\Models\InvoiceItem;
use App\Models\ShipmentItem;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * StockMovementService
 * 
 * تقرير حركة المخزون
 * 
 * يعرض حركات الدخول والخروج خلال فترة محددة
 * 
 * @package App\Services\Reports
 */
class StockMovementService extends BaseService
{
    /**
     * Generate stock movement report for a period.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Report data
     */
    public function generate(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $incoming = $this->getIncoming($dateFrom, $dateTo);
        $outgoing = $this->getOutgoing($dateFrom, $dateTo);
        $carryover = $this->getCarryover($dateFrom, $dateTo);

        // Build products array by aggregating incoming and outgoing per product
        $productsData = [];

        // Process incoming (shipments)
        foreach ($incoming as $item) {
            $productName = $item->product_name ?? 'Unknown';
            if (!isset($productsData[$productName])) {
                $productsData[$productName] = [
                    'product_name' => $productName,
                    'incoming' => ['cartons' => 0, 'weight' => 0],
                    'outgoing' => ['cartons' => 0, 'weight' => 0],
                    'carryover' => ['in' => 0, 'out' => 0],
                    'net_change' => ['cartons' => 0, 'weight' => 0],
                ];
            }
            $productsData[$productName]['incoming']['cartons'] += $item->cartons ?? 0;
            $productsData[$productName]['incoming']['weight'] += $item->weight ?? 0;
        }

        // Process outgoing (sales)
        foreach ($outgoing as $item) {
            $productName = $item->product_name ?? 'Unknown';
            if (!isset($productsData[$productName])) {
                $productsData[$productName] = [
                    'product_name' => $productName,
                    'incoming' => ['cartons' => 0, 'weight' => 0],
                    'outgoing' => ['cartons' => 0, 'weight' => 0],
                    'carryover' => ['in' => 0, 'out' => 0],
                    'net_change' => ['cartons' => 0, 'weight' => 0],
                ];
            }
            $productsData[$productName]['outgoing']['cartons'] += $item->cartons ?? 0;
            $productsData[$productName]['outgoing']['weight'] += $item->weight ?? 0;
        }

        // Process carryover
        foreach ($carryover['in'] as $item) {
            $productName = $item['product_name'] ?? 'Unknown';
            if (!isset($productsData[$productName])) {
                $productsData[$productName] = [
                    'product_name' => $productName,
                    'incoming' => ['cartons' => 0, 'weight' => 0],
                    'outgoing' => ['cartons' => 0, 'weight' => 0],
                    'carryover' => ['in' => 0, 'out' => 0],
                    'net_change' => ['cartons' => 0, 'weight' => 0],
                ];
            }
            $productsData[$productName]['carryover']['in'] += $item['cartons'] ?? 0;
        }

        // Calculate net change for each product
        foreach ($productsData as &$product) {
            $product['net_change']['cartons'] =
                $product['incoming']['cartons'] + $product['carryover']['in'] -
                $product['outgoing']['cartons'] - $product['carryover']['out'];
            $product['net_change']['weight'] =
                $product['incoming']['weight'] - $product['outgoing']['weight'];
        }

        $products = array_values($productsData);

        // Calculate totals
        $totalIncomingCartons = $incoming->sum('cartons') + $carryover['in']->sum('cartons');
        $totalIncomingWeight = $incoming->sum('weight');
        $totalOutgoingCartons = $outgoing->sum('quantity');
        $totalOutgoingWeight = $outgoing->sum('weight');
        $totalCarryoverIn = $carryover['in']->sum('cartons');
        $totalCarryoverOut = $carryover['out']->sum('cartons');

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'products' => $products,
            'totals' => [
                'incoming' => [
                    'cartons' => $incoming->sum('cartons'),
                    'weight' => $totalIncomingWeight,
                ],
                'outgoing' => [
                    'cartons' => $totalOutgoingCartons,
                    'weight' => $totalOutgoingWeight,
                ],
                'carryover' => [
                    'in' => $totalCarryoverIn,
                    'out' => $totalCarryoverOut,
                ],
                'net_change' => [
                    'cartons' => $totalIncomingCartons - $totalOutgoingCartons - $totalCarryoverOut,
                    'weight' => $totalIncomingWeight - $totalOutgoingWeight,
                ],
            ],
            'summary' => [
                'products_count' => count($products),
                'shipments_received' => $incoming->count(),
                'invoices_issued' => $outgoing->groupBy('invoice_number')->count(),
            ],
        ];
    }

    /**
     * Get incoming stock (new shipments).
     */
    protected function getIncoming(?string $dateFrom, ?string $dateTo)
    {
        return DB::table('shipment_items')
            ->join('shipments', 'shipment_items.shipment_id', '=', 'shipments.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->join('suppliers', 'shipments.supplier_id', '=', 'suppliers.id')
            ->when($dateFrom, fn($q) => $q->whereDate('shipments.date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('shipments.date', '<=', $dateTo))
            ->selectRaw('
                shipments.number as shipment_number,
                shipments.date as shipment_date,
                suppliers.name as supplier_name,
                products.name_en as product_name,
                shipment_items.cartons,
                shipment_items.cartons * shipment_items.weight_per_unit as weight
            ')
            ->orderBy('shipments.date')
            ->get();
    }

    /**
     * Get outgoing stock (sales).
     */
    protected function getOutgoing(?string $dateFrom, ?string $dateTo)
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->where('invoices.status', 'active')
            ->when($dateFrom, fn($q) => $q->whereDate('invoices.date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('invoices.date', '<=', $dateTo))
            ->selectRaw('
                invoices.invoice_number,
                invoices.date as sale_date,
                COALESCE(products.name_en, products.name) as product_name,
                invoice_items.cartons,
                invoice_items.quantity as weight
            ')
            ->orderBy('invoices.date')
            ->get();
    }

    /**
     * Get carryover movements.
     */
    protected function getCarryover(?string $dateFrom, ?string $dateTo): array
    {
        $query = Carryover::with(['product', 'fromShipment', 'toShipment'])
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo));

        $carryovers = $query->get();

        return [
            'in' => $carryovers->map(fn($c) => [
                'product_name' => $c->product->name_en ?? $c->product->name ?? 'N/A',
                'from_shipment' => $c->fromShipment->number ?? 'N/A',
                'to_shipment' => $c->toShipment->number ?? 'N/A',
                'cartons' => $c->cartons,
                'reason' => $c->reason,
            ]),
            'out' => collect(), // Carryover out is the same as in, just different perspective
        ];
    }

    protected function getServiceName(): string
    {
        return 'StockMovementService';
    }
}
