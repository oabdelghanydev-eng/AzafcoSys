<?php
/**
 * Simple Settlement Report for closed shipments
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Shipment;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

$shipments = Shipment::where('status', 'closed')->with(['supplier', 'items.product'])->get();

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           SHIPMENT SETTLEMENT REPORTS                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

foreach ($shipments as $shipment) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📦 Shipment: {$shipment->number}\n";
    echo "📅 Date: {$shipment->date}\n";
    echo "🏢 Supplier: {$shipment->supplier->name}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Items Summary
    echo "📊 Items Summary:\n";
    echo str_pad("Product", 20) . str_pad("Cartons", 12) . str_pad("Sold", 12) . str_pad("Weight/Unit", 15) . "Total Weight\n";
    echo str_repeat("-", 75) . "\n";
    
    $totalCartons = 0;
    $totalSold = 0;
    $totalWeight = 0;
    
    foreach ($shipment->items as $item) {
        $weight = $item->sold_cartons * $item->weight_per_unit;
        echo str_pad($item->product->name, 20);
        echo str_pad($item->cartons, 12);
        echo str_pad($item->sold_cartons, 12);
        echo str_pad(number_format($item->weight_per_unit, 2) . " kg", 15);
        echo number_format($weight, 2) . " kg\n";
        
        $totalCartons += $item->cartons;
        $totalSold += $item->sold_cartons;
        $totalWeight += $weight;
    }
    
    echo str_repeat("-", 75) . "\n";
    echo str_pad("TOTAL", 20) . str_pad($totalCartons, 12) . str_pad($totalSold, 12) . str_pad("", 15) . number_format($totalWeight, 2) . " kg\n\n";
    
    // Sales Summary
    $sales = DB::table('invoice_items')
        ->join('shipment_items', 'invoice_items.shipment_item_id', '=', 'shipment_items.id')
        ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
        ->where('shipment_items.shipment_id', $shipment->id)
        ->where('invoices.status', 'active')
        ->select(
            DB::raw('SUM(invoice_items.quantity) as qty'),
            DB::raw('SUM(invoice_items.subtotal) as total')
        )
        ->first();
    
    $totalSales = $sales->total ?? 0;
    $commission = $totalSales * 0.06; // 6%
    $netSales = $totalSales - $commission;
    
    echo "💰 Sales Summary:\n";
    echo "   Total Sales:        " . number_format($totalSales, 2) . " AED\n";
    echo "   Commission (6%):    " . number_format($commission, 2) . " AED\n";
    echo "   Net to Supplier:    " . number_format($netSales, 2) . " AED\n\n";
    
    echo "✅ Status: CLOSED\n\n";
}

// Overall Summary
$totalCartons = \App\Models\ShipmentItem::whereHas('shipment', fn($q) => $q->where('status', 'closed'))->sum('cartons');
$totalSold = \App\Models\ShipmentItem::whereHas('shipment', fn($q) => $q->where('status', 'closed'))->sum('sold_cartons');

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                    OVERALL SUMMARY                         ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
echo "║  Closed Shipments:    " . str_pad($shipments->count(), 35) . "║\n";
echo "║  Total Cartons:       " . str_pad($totalCartons, 35) . "║\n";
echo "║  Total Sold:          " . str_pad($totalSold, 35) . "║\n";
echo "║  Sell Rate:           " . str_pad(round($totalSold / max($totalCartons, 1) * 100, 1) . "%", 35) . "║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
