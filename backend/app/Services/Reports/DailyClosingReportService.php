<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Transfer;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Customer;
use App\Models\Account;
use Illuminate\Support\Collection as LaravelCollection;

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
                    'product_name' => $item->product->name_ar ?? $item->product->name_en,
                    'quantity' => $item->quantity,
                    'weight_per_unit' => $weightPerUnit,
                    'total_weight' => $item->quantity * $weightPerUnit,
                    'subtotal' => $item->subtotal,
                ];
            });

        $data['invoiceItems'] = $invoiceItems;
        $data['totalQuantity'] = $invoiceItems->sum('quantity');
        $data['totalWeight'] = $invoiceItems->sum('total_weight');
        $data['totalSales'] = $invoiceItems->sum('subtotal');

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
        $data['totalExpenses'] = $expenses->sum('amount');

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

        // 7. Remaining Stock - Fixed: selectRaw + groupBy doesn't work with with()
        $remainingStockRaw = ShipmentItem::whereHas('shipment', function ($q) {
            $q->whereIn('status', ['open', 'closed']);
        })
            ->where('remaining_quantity', '>', 0)
            ->selectRaw('
                product_id,
                SUM(remaining_quantity) as total_quantity,
                SUM(remaining_quantity * weight_per_unit) as total_weight
            ')
            ->groupBy('product_id')
            ->get();

        // Load products separately and attach
        $productIds = $remainingStockRaw->pluck('product_id')->toArray();
        $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');

        $data['remainingStock'] = $remainingStockRaw->map(function ($item) use ($products) {
            $item->product = $products->get($item->product_id);
            return $item;
        });

        return $data;
    }
}
