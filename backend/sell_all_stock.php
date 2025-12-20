<?php
/**
 * API Simulation: Sell All Shipment Stock & Close
 * Mimics frontend API workflow
 */
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\{Customer, Invoice, InvoiceItem, Shipment, ShipmentItem, User, Account};
use App\Services\FifoAllocatorService;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     API SIMULATION: SELL ALL STOCK & CLOSE SHIPMENT       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$user = User::first();
$date = date('Y-m-d');
$ts = time();

// 1. Get or Create Customer
echo "1. Customer Setup...\n";
$customer = Customer::first() ?? Customer::create([
    'code' => 'CUST-API-' . $ts,
    'name' => 'API Test Customer',
    'phone' => '0561234567',
    'balance' => 0,
]);
echo "   Customer: {$customer->name} (Balance: {$customer->balance})\n";

// 2. Get All Shipments with Remaining Stock
echo "\n2. Analyzing Shipments...\n";
$shipments = Shipment::where('status', 'open')->with('items.product')->get();
echo "   Found " . $shipments->count() . " open shipments\n";

$fifo = app(FifoAllocatorService::class);
$totalInvoices = 0;
$totalSales = 0;

// 3. For Each Shipment, Create Invoice to Sell ALL Remaining
foreach ($shipments as $shipment) {
    echo "\n━━━ Shipment #{$shipment->number} ━━━\n";

    $invoiceItems = [];
    $subtotal = 0;

    foreach ($shipment->items as $item) {
        $remaining = $item->cartons - $item->sold_cartons;
        if ($remaining <= 0) {
            echo "   ⏭ {$item->product->name}: No stock remaining\n";
            continue;
        }

        // Calculate values
        $weightPerUnit = $item->weight_per_unit;
        $totalWeight = $remaining * $weightPerUnit;
        $pricePerKg = round(rand(45, 65), 2); // Random price 45-65 AED/kg
        $itemTotal = round($totalWeight * $pricePerKg, 2);

        echo "   📦 {$item->product->name}: $remaining cartons × {$weightPerUnit}kg = {$totalWeight}kg @ {$pricePerKg} AED/kg = {$itemTotal} AED\n";

        $invoiceItems[] = [
            'shipment_item' => $item,
            'cartons' => $remaining,
            'weight' => $totalWeight,
            'price' => $pricePerKg,
            'total' => $itemTotal,
        ];
        $subtotal += $itemTotal;
    }

    if (empty($invoiceItems)) {
        echo "   ⚠ No items to sell from this shipment\n";
        continue;
    }

    // Create Invoice
    echo "\n   Creating Invoice...\n";
    $invoice = Invoice::create([
        'invoice_number' => 'INV-FULL-' . $ts . '-' . $shipment->id,
        'customer_id' => $customer->id,
        'date' => $date,
        'subtotal' => $subtotal,
        'discount' => 0,
        'total' => $subtotal,
        'paid_amount' => 0,
        'balance' => $subtotal,
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    // Allocate using FIFO
    foreach ($invoiceItems as $item) {
        $fifo->allocateAndCreate(
            $invoice->id,
            $item['shipment_item']->product_id,
            $item['cartons'],
            $item['weight'],
            $item['price']
        );
    }

    // Update customer balance (Invoice observer doesn't do this)
    $customer->increment('balance', $subtotal);

    echo "   ✅ Invoice #{$invoice->invoice_number}: " . number_format($subtotal, 2) . " AED\n";
    $totalInvoices++;
    $totalSales += $subtotal;

    // Verify all sold
    $shipment->refresh();
    $remainingAfter = $shipment->items->sum(fn($i) => $i->cartons - $i->sold_cartons);
    echo "   📊 Remaining after sale: $remainingAfter cartons\n";

    if ($remainingAfter == 0) {
        echo "   🔒 Closing shipment...\n";
        $shipment->update(['status' => 'closed']);
        echo "   ✅ Shipment closed!\n";
    }
}

// 4. Summary
echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║                    SIMULATION SUMMARY                      ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║ %-25s %30s ║\n", "Invoices Created:", $totalInvoices);
printf("║ %-25s %27s AED ║\n", "Total Sales:", number_format($totalSales, 2));
printf("║ %-25s %27s AED ║\n", "Customer Balance:", number_format($customer->fresh()->balance, 2));

// Verify remaining
$totalRemaining = ShipmentItem::sum(\DB::raw('cartons - sold_cartons'));
printf("║ %-25s %30s ║\n", "Remaining Stock:", $totalRemaining . " cartons");
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 5. Generate Settlement Reports
echo "Generating Settlement Reports...\n";
foreach ($shipments as $shipment) {
    if ($shipment->fresh()->status === 'closed') {
        echo "   📄 Settlement Report for {$shipment->number}\n";
        echo "   Run: php artisan report:settlement {$shipment->id}\n";
    }
}
