<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ShipmentItem;
use App\Models\InvoiceItem;

echo "=== الوارد (الشحنات) ===\n\n";
$shipmentItems = ShipmentItem::with(['product', 'shipment'])->get()
    ->groupBy('product_id');

$totalsShipment = [];
foreach ($shipmentItems as $productId => $items) {
    $totalCartons = $items->sum('cartons');
    $productName = $items->first()->product->name;
    $totalsShipment[$productId] = $totalCartons;
    echo sprintf("%-15s | كراتين واردة: %5d\n", $productName, $totalCartons);
}

echo "\n=== المباع (الفواتير) ===\n\n";
$invoiceItems = InvoiceItem::whereHas('invoice', function ($q) {
    $q->where('status', 'active');
})->with(['product'])->get()->groupBy('product_id');

foreach ($invoiceItems as $productId => $items) {
    $totalSold = $items->sum('quantity'); // quantity = cartons sold
    $productName = $items->first()->product->name;
    $received = $totalsShipment[$productId] ?? 0;
    $diff = $received - $totalSold;

    echo sprintf(
        "%-15s | كراتين مباعة: %5.0f | واردة: %5d | الفرق: %5.0f %s\n",
        $productName,
        $totalSold,
        $received,
        abs($diff),
        $diff < 0 ? '⚠️ خطأ!' : '✓'
    );
}
