<?php

namespace App\Services\Reports;

use App\Models\Carryover;
use App\Models\Expense;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class ShipmentSettlementReportService
{
    /**
     * Company commission rate (default 6%)
     */
    private function getCommissionRate(): float
    {
        return (float) (config('settings.company_commission_rate', 6)) / 100;
    }

    /**
     * Generate shipment settlement report data
     */
    public function generate(Shipment $shipment): array
    {
        $shipment->load(['supplier', 'items.product']);

        $data = [];

        // 1. Basic Info
        $data['shipment'] = $shipment;
        $data['supplier'] = $shipment->supplier;
        $data['arrivalDate'] = $shipment->date;
        $data['settlementDate'] = now()->format('Y-m-d');
        $data['durationDays'] = $shipment->date->diffInDays(now());

        // 2. Sales by Product
        $salesByProduct = $this->getSalesByProduct($shipment);
        $data['salesByProduct'] = $salesByProduct;
        $data['totalSalesAmount'] = $salesByProduct->sum('total');
        $data['totalSoldQuantity'] = $salesByProduct->sum('quantity');
        $data['totalSoldWeight'] = $salesByProduct->sum('weight');

        // 3. Returns from Previous Shipment
        $previousShipment = $this->getPreviousShipment($shipment);
        $previousReturns = collect();
        $totalReturnsValue = 0;

        if ($previousShipment) {
            $previousReturns = Carryover::where('from_shipment_id', $previousShipment->id)
                ->where('reason', 'late_return')
                ->where('to_shipment_id', $shipment->id)
                ->with('product')
                ->get();

            $totalReturnsValue = $this->calculateReturnsValue($previousReturns);
        }

        $data['previousShipmentReturns'] = $previousReturns;
        $data['totalReturnsQuantity'] = $previousReturns->sum('cartons');
        $data['totalReturnsWeight'] = 0; // Calculate if needed
        $data['totalReturnsValue'] = $totalReturnsValue;

        // 4. Inventory Movement - Load with fromShipmentItem for weight_per_unit

        // 4.1 Incoming Items (الوارد الأصلي من الشحنة)
        $data['incomingItems'] = $shipment->items->map(function ($item) {
            return (object) [
                'product' => $item->product,
                'cartons' => $item->cartons,
                'weight_per_unit' => $item->weight_per_unit,
                'weight_label' => $item->weight_label,
            ];
        });
        $data['totalIncomingCartons'] = $shipment->items->sum('cartons');

        // 4.2 Carryover In (المرحل من الشحنة السابقة)
        $data['carryoverIn'] = Carryover::where('to_shipment_id', $shipment->id)
            ->where('reason', 'end_of_shipment')
            ->with(['product', 'fromShipmentItem'])
            ->get();

        // 4.3 Returns In (مرتجعات من شحنة سابقة)
        $data['returnsIn'] = Carryover::where('to_shipment_id', $shipment->id)
            ->where('reason', 'late_return')
            ->with(['product', 'fromShipmentItem'])
            ->get();

        // 4.4 Carryover Out (المرحل للشحنة التالية)
        $data['carryoverOut'] = Carryover::where('from_shipment_id', $shipment->id)
            ->where('reason', 'end_of_shipment')
            ->with(['product', 'fromShipmentItem'])
            ->get();

        // 5. Weight Analysis
        // الوزن الوارد الكلي (الأصلي + المرحل للداخل + المرتجعات)
        $data['totalWeightIn'] = $this->calculateTotalWeightIn($shipment, $data);

        // الوزن المرحل للخارج
        $data['totalCarryoverOutWeight'] = $this->calculateCarryoverOutWeight($data);

        // الوزن الوارد الفعلي = الوزن الوارد - المرحل للخارج
        $data['effectiveWeightIn'] = $data['totalWeightIn'] - $data['totalCarryoverOutWeight'];

        // الوزن المباع فعلياً
        $data['totalWeightOut'] = $data['totalSoldWeight'];

        // الهالك = الوزن الوارد الفعلي - الوزن المباع
        $data['weightDifference'] = $data['effectiveWeightIn'] - $data['totalWeightOut'];

        // 6. Supplier Expenses (linked to this shipment only)
        $supplierExpenses = Expense::where('shipment_id', $shipment->id)
            ->where('type', 'supplier')
            ->get();
        $data['supplierExpenses'] = $supplierExpenses;
        $data['totalSupplierExpenses'] = $supplierExpenses->sum('amount');

        // 6.5 Credit/Debit Notes (Price Adjustments during shipment)
        $creditNotes = \App\Models\CreditNote::whereHas('invoice', function ($q) use ($shipment) {
            $q->whereHas('items', function ($q2) use ($shipment) {
                $q2->whereHas('shipmentItem', function ($q3) use ($shipment) {
                    $q3->where('shipment_id', $shipment->id);
                });
            });
        })
            ->where('status', 'active')
            ->with(['customer', 'invoice'])
            ->get();

        $data['creditNotes'] = $creditNotes;
        $data['totalCreditNotes'] = $creditNotes->where('type', 'credit')->sum('amount');
        $data['totalDebitNotes'] = $creditNotes->where('type', 'debit')->sum('amount');
        $data['netAdjustments'] = $data['totalDebitNotes'] - $data['totalCreditNotes'];

        // 7. Financial Calculation (Correct Order)
        $data['totalSales'] = $data['totalSalesAmount'];
        $data['previousReturnsDeduction'] = $totalReturnsValue;
        $data['priceAdjustments'] = $data['netAdjustments']; // Credit reduces, Debit increases
        $data['netSales'] = $data['totalSales'] - $data['previousReturnsDeduction'] + $data['priceAdjustments'];
        $data['companyCommission'] = $data['netSales'] * $this->getCommissionRate();
        $data['supplierExpensesDeduction'] = $data['totalSupplierExpenses'];

        // Get previous balance from last settled shipment of this supplier
        // This ensures the balance chain is maintained correctly
        $data['previousBalance'] = $this->getPreviousSupplierBalance($shipment);

        $data['supplierPayments'] = $this->getSupplierPayments($shipment);

        $data['finalSupplierBalance'] =
            $data['netSales']
            - $data['companyCommission']
            - $data['supplierExpensesDeduction']
            + $data['previousBalance']
            - $data['supplierPayments'];

        return $data;
    }

    /**
     * Get sales by product for this shipment
     */
    private function getSalesByProduct(Shipment $shipment)
    {
        $sales = DB::table('invoice_items')
            ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'shipment_items.product_id', '=', 'products.id')
            ->where('shipment_items.shipment_id', $shipment->id)
            ->where('invoices.status', 'active')
            ->selectRaw('
                products.id as product_id,
                products.name as name_ar,
                products.name_en as name_en,
                SUM(invoice_items.cartons) as quantity,
                SUM(invoice_items.quantity) as weight,
                SUM(invoice_items.subtotal) as total,
                AVG(invoice_items.unit_price) as avg_price
            ')
            ->groupBy('products.id', 'products.name', 'products.name_en')
            ->get();

        // Add bilingual product_name
        return $sales->map(function ($sale) {
            $sale->product_name = trim($sale->name_ar) . ' / ' . trim($sale->name_en);
            return $sale;
        });
    }

    /**
     * Get previous shipment from same supplier
     */
    private function getPreviousShipment(Shipment $shipment): ?Shipment
    {
        return Shipment::where('supplier_id', $shipment->supplier_id)
            ->where('id', '<', $shipment->id)
            ->where('status', 'settled')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Calculate total value of returns
     */
    private function calculateReturnsValue($returns): float
    {
        // For now, return 0 if no way to calculate
        // In a full implementation, this would calculate based on original sale price
        return 0;
    }

    /**
     * Calculate total weight in (incoming + carryover in + returns in)
     */
    private function calculateTotalWeightIn(Shipment $shipment, array $data): float
    {
        $incomingWeight = $shipment->items->sum(function ($item) {
            return $item->cartons * $item->weight_per_unit;
        });

        // Get weight_per_unit from fromShipmentItem relationship
        $carryoverInWeight = $data['carryoverIn']->sum(function ($co) {
            $weightPerUnit = $co->fromShipmentItem?->weight_per_unit ?? 0;

            return $co->cartons * $weightPerUnit;
        });

        $returnsInWeight = $data['returnsIn']->sum(function ($ret) {
            $weightPerUnit = $ret->fromShipmentItem?->weight_per_unit ?? 0;

            return $ret->cartons * $weightPerUnit;
        });

        return $incomingWeight + $carryoverInWeight + $returnsInWeight;
    }

    /**
     * Calculate total weight out (sold + carryover out)
     */
    private function calculateTotalWeightOut(Shipment $shipment, array $data): float
    {
        $soldWeight = $data['totalSoldWeight'];

        $carryoverOutWeight = $data['carryoverOut']->sum(function ($co) {
            $weightPerUnit = $co->fromShipmentItem?->weight_per_unit ?? 0;

            return $co->cartons * $weightPerUnit;
        });

        return $soldWeight + $carryoverOutWeight;
    }

    /**
     * Calculate carryover out weight only
     * الوزن المرحل للخارج فقط
     */
    private function calculateCarryoverOutWeight(array $data): float
    {
        return $data['carryoverOut']->sum(function ($co) {
            $weightPerUnit = $co->fromShipmentItem?->weight_per_unit ?? 0;

            return $co->cartons * $weightPerUnit;
        });
    }

    /**
     * Get supplier payments during shipment period
     */
    private function getSupplierPayments(Shipment $shipment): float
    {
        return Expense::where('supplier_id', $shipment->supplier_id)
            ->where('type', 'supplier_payment')
            ->whereBetween('date', [$shipment->date, now()])
            ->sum('amount');
    }

    /**
     * Get the previous balance from last settled shipment
     * This creates a chain of balances for accurate reporting
     */
    private function getPreviousSupplierBalance(Shipment $shipment): float
    {
        // Find the previous settled shipment for this supplier
        $previousSettledShipment = Shipment::where('supplier_id', $shipment->supplier_id)
            ->where('id', '<', $shipment->id)
            ->where('status', 'settled')
            ->orderBy('id', 'desc')
            ->first();

        if ($previousSettledShipment) {
            // Return the stored final balance from previous shipment
            return (float) ($previousSettledShipment->final_supplier_balance ?? 0);
        }

        // No previous shipment - use supplier's opening balance
        return (float) ($shipment->supplier->opening_balance ?? 0);
    }
}
