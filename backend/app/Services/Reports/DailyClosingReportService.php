<?php

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Collection;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Transfer;

class DailyClosingReportService
{
    /**
     * Generate daily closing report data
     */
    public function generate(string $date): array
    {
        $data = [
            'date' => $date,
        ];

        // 1. Invoice Items (on item level)
        // cartons = عدد الكراتين المباعة
        // quantity = الوزن الفعلي من الميزان (kg)
        $invoiceItems = InvoiceItem::whereHas('invoice', function ($q) use ($date) {
            $q->where('date', $date)->where('status', 'active');
        })
            ->with(['invoice.customer', 'product', 'shipmentItem'])
            ->get()
            ->map(function ($item) {
                $weightPerUnit = $item->shipmentItem?->weight_per_unit ?? 0;

                return [
                    'invoice_number' => $item->invoice->invoice_number,
                    'customer_name' => $item->invoice->customer->name,
                    'product_name' => $item->product->bilingual_name,
                    'cartons' => $item->cartons,                    // عدد الكراتين
                    'weight_per_unit' => $weightPerUnit,            // وزن الكرتونة (من الشحنة)
                    'total_weight' => $item->quantity,              // الوزن الفعلي (من الميزان)
                    'price' => $item->unit_price,                   // سعر الكيلو
                    'subtotal' => $item->subtotal,
                ];
            });

        $data['invoiceItems'] = $invoiceItems;
        $data['totalCartons'] = $invoiceItems->sum('cartons');
        $data['totalWeight'] = $invoiceItems->sum('total_weight');
        $data['totalSales'] = $invoiceItems->sum('subtotal');

        // Daily Wastage Calculation per product (from sales)
        // Wastage = (sold_cartons × weight_per_unit) - actual_sold_quantity
        $wastageByProduct = InvoiceItem::whereHas('invoice', function ($q) use ($date) {
            $q->where('date', $date)->where('status', 'active');
        })
            ->with(['product', 'shipmentItem'])
            ->get()
            ->groupBy('product_id')
            ->map(function ($items, $productId) {
                $product = $items->first()->product;

                $totalCartonsSold = $items->sum('cartons');
                $totalExpectedWeight = $items->sum(function ($item) {
                    $weightPerUnit = $item->shipmentItem?->weight_per_unit ?? 0;
                    return $item->cartons * $weightPerUnit;
                });
                $totalActualWeight = $items->sum('quantity');
                $wastage = $totalExpectedWeight - $totalActualWeight;

                return [
                    'product_id' => $productId,
                    'product_name' => $product->bilingual_name,
                    'cartons_sold' => $totalCartonsSold,
                    'expected_weight' => $totalExpectedWeight,
                    'actual_weight' => $totalActualWeight,
                    'wastage' => $wastage,
                ];
            })
            ->values();

        // Returns Section - استرداد العجز من المرتجعات
        $returns = \App\Models\ReturnModel::where('date', $date)
            ->where('status', 'active')
            ->with(['customer', 'items.product', 'items.originalInvoiceItem'])
            ->get();

        // Calculate return items with recovered wastage
        $returnItems = collect();
        $recoveredWastageByProduct = collect();

        foreach ($returns as $return) {
            foreach ($return->items as $item) {
                $originalInvoice = $item->originalInvoiceItem;

                // Calculate weight based on original invoice actual weight per carton
                // وزن المرتجع = عدد الكراتين × (الوزن الفعلي المباع ÷ عدد الكراتين في الفاتورة)
                $actualWeightPerCarton = 0;
                $wastagePerCarton = 0;

                if ($originalInvoice && $originalInvoice->cartons > 0) {
                    $actualWeightPerCarton = $originalInvoice->quantity / $originalInvoice->cartons;
                    $expectedWeightPerCarton = $originalInvoice->shipmentItem?->weight_per_unit ?? 0;
                    $wastagePerCarton = $expectedWeightPerCarton - $actualWeightPerCarton;
                }

                $returnedWeight = $item->cartons * $actualWeightPerCarton;
                $recoveredWastage = $item->cartons * $wastagePerCarton;

                $returnItems->push([
                    'return_number' => $return->return_number,
                    'customer_name' => $return->customer->name,
                    'product_name' => $item->product->bilingual_name,
                    'cartons' => $item->cartons,
                    'calculated_weight' => $returnedWeight,
                    'recovered_wastage' => $recoveredWastage,
                    'subtotal' => $item->subtotal,
                ]);

                // Aggregate recovered wastage by product
                $productId = $item->product_id;
                if (!$recoveredWastageByProduct->has($productId)) {
                    $recoveredWastageByProduct[$productId] = [
                        'product_id' => $productId,
                        'product_name' => $item->product->bilingual_name,
                        'returned_cartons' => 0,
                        'returned_weight' => 0,
                        'recovered_wastage' => 0,
                    ];
                }
                $recoveredWastageByProduct[$productId]['returned_cartons'] += $item->cartons;
                $recoveredWastageByProduct[$productId]['returned_weight'] += $returnedWeight;
                $recoveredWastageByProduct[$productId]['recovered_wastage'] += $recoveredWastage;
            }
        }

        $data['returns'] = $returns;
        $data['returnItems'] = $returnItems;
        $data['totalReturnsCartons'] = $returnItems->sum('cartons');
        $data['totalReturnsWeight'] = $returnItems->sum('calculated_weight');
        $data['totalReturnsValue'] = $returnItems->sum('subtotal');
        $data['recoveredWastageByProduct'] = $recoveredWastageByProduct->values();
        $data['totalRecoveredWastage'] = $recoveredWastageByProduct->sum('recovered_wastage');

        // Net Wastage = Sales Wastage - Recovered Wastage
        $data['wastageByProduct'] = $wastageByProduct;
        $data['totalWastage'] = $wastageByProduct->sum('wastage');
        $data['netWastage'] = $data['totalWastage'] - $data['totalRecoveredWastage'];

        // 2. Collections
        $collections = Collection::where('date', $date)
            ->with('customer')
            ->get();

        $data['collections'] = $collections;
        $data['totalCollectionsCash'] = $collections->where('payment_method', 'cash')->sum('amount');
        $data['totalCollectionsBank'] = $collections->where('payment_method', 'bank')->sum('amount');
        $data['totalCollections'] = $collections->sum('amount');

        // 3. Expenses
        $expenses = Expense::where('date', $date)->get();

        $data['expenses'] = $expenses;
        $data['totalExpensesCash'] = $expenses->where('payment_method', 'cash')->sum('amount');
        $data['totalExpensesBank'] = $expenses->where('payment_method', 'bank')->sum('amount');
        $data['totalExpensesCompany'] = $expenses->where('type', 'company')->sum('amount');
        $data['totalExpensesSupplier'] = $expenses->where('type', 'supplier')->sum('amount');
        $data['totalSupplierPayments'] = $expenses->where('type', 'supplier_payment')->sum('amount');
        $data['totalExpenses'] = $expenses->sum('amount');

        // 3.5 Credit/Debit Notes (Price Adjustments)
        $creditNotes = CreditNote::where('date', $date)
            ->where('status', 'active')
            ->with(['customer', 'invoice'])
            ->get();

        $data['creditNotes'] = $creditNotes;
        $data['totalCreditNotes'] = $creditNotes->where('type', 'credit')->sum('amount');
        $data['totalDebitNotes'] = $creditNotes->where('type', 'debit')->sum('amount');
        $data['netAdjustments'] = $data['totalDebitNotes'] - $data['totalCreditNotes'];

        // 4. Transfers
        $data['transfers'] = Transfer::whereDate('created_at', $date)
            ->with(['fromAccount', 'toAccount'])
            ->get();

        // 5. New Shipments
        $data['newShipments'] = Shipment::where('date', $date)
            ->with(['supplier', 'items'])
            ->get();

        // 6. Balances
        $data['marketBalance'] = Customer::where('balance', '>', 0)->sum('balance');
        $data['cashboxBalance'] = Account::where('type', 'cashbox')->first()?->balance ?? 0;
        $data['bankBalance'] = Account::where('type', 'bank')->first()?->balance ?? 0;

        // 7. Remaining Stock (Cartons-Based)
        // Use the remaining_cartons accessor which computes: cartons + carryover_in - sold - carryover_out
        $remainingStockRaw = ShipmentItem::whereHas('shipment', function ($q) {
            $q->whereIn('status', ['open', 'closed']);
        })
            ->with('product')
            ->get()
            ->filter(fn($item) => $item->remaining_cartons > 0)
            ->groupBy('product_id')
            ->map(function ($items, $productId) {
                $first = $items->first();
                $totalCartons = $items->sum('remaining_cartons');
                // Expected weight = cartons × average weight_per_unit
                $totalWeight = $items->sum(fn($item) => $item->remaining_cartons * $item->weight_per_unit);

                return (object) [
                    'product_id' => $productId,
                    'product' => $first->product,
                    'remaining_cartons' => $totalCartons,
                    'total_weight_kg' => $totalWeight,
                ];
            })
            ->values();

        // Convert wastageByProduct to keyed array for quick lookup
        $wastageMap = $wastageByProduct->keyBy('product_id');

        $data['remainingStock'] = $remainingStockRaw->map(function ($item) use ($wastageMap) {
            // Attach daily wastage if exists
            $wastageData = $wastageMap->get($item->product_id);
            $item->daily_wastage = $wastageData['wastage'] ?? 0;

            return $item;
        });

        return $data;
    }
}
