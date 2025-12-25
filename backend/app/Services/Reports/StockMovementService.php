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

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'incoming' => [
                'shipments' => $incoming,
                'total_cartons' => $incoming->sum('cartons'),
                'total_weight' => $incoming->sum('weight'),
            ],
            'outgoing' => [
                'sales' => $outgoing,
                'total_cartons' => $outgoing->sum('quantity'),
                'total_weight' => $outgoing->sum('weight'),
            ],
            'carryover' => [
                'in' => $carryover['in'],
                'out' => $carryover['out'],
                'in_total' => $carryover['in']->sum('cartons'),
                'out_total' => $carryover['out']->sum('cartons'),
            ],
            'summary' => [
                'net_in' => $incoming->sum('cartons') + $carryover['in']->sum('cartons'),
                'net_out' => $outgoing->sum('quantity') + $carryover['out']->sum('cartons'),
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
                products.name_ar as product_name,
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
                products.name_ar as product_name,
                invoice_items.quantity,
                invoice_items.quantity * shipment_items.weight_per_unit as weight
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
                'product_name' => $c->product->name_ar ?? 'N/A',
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
