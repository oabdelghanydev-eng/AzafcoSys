<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InvoiceItem;

echo "=== Invoice Items for 2025-12-19 ===\n\n";

$items = InvoiceItem::whereHas('invoice', function ($q) {
    $q->where('date', '2025-12-19')->where('status', 'active');
})->with(['product', 'shipmentItem'])->get();

foreach ($items as $i) {
    $wpu = $i->shipmentItem->weight_per_unit ?? 0;
    $expected = $i->cartons * $wpu;
    $actual = $i->quantity;
    $wastage = $expected - $actual;

    echo "Product: " . ($i->product->name ?? 'N/A') . "\n";
    echo "  - Cartons: {$i->cartons}\n";
    echo "  - Weight per unit (shipment): $wpu kg\n";
    echo "  - Expected weight: $expected kg\n";
    echo "  - Actual weight (sold): $actual kg\n";
    echo "  - WASTAGE: $wastage kg\n";
    echo "  - Subtotal: {$i->subtotal}\n";
    echo "\n";
}
