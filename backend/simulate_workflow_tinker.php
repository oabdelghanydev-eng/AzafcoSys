<?php
/**
 * Daily Workflow Simulation Command
 * Run with: php artisan tinker < simulate_workflow_tinker.php
 */

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\DailyReport;
use App\Models\User;
use App\Services\FifoAllocatorService;
use Illuminate\Support\Facades\DB;

$today = now()->toDateString();
echo "=== Daily Workflow Simulation ===\n";
echo "Date: $today\n\n";

// 1. Get or create required data
echo "1. Setting up data...\n";
$supplier = Supplier::first() ?? Supplier::factory()->create();
$customer = Customer::first() ?? Customer::factory()->create();
$products = Product::take(3)->get();
$user = User::first();

echo "   Supplier: {$supplier->name}\n";
echo "   Customer: {$customer->name}\n";
echo "   Products: " . $products->count() . "\n\n";

// 2. Open Daily Report (simulate session)
echo "2. Opening daily report...\n";
$dailyReport = DailyReport::firstOrCreate(
    ['date' => $today],
    ['status' => 'open', 'opened_by' => $user->id ?? 1]
);
echo "   ✅ Day opened (ID: {$dailyReport->id})\n\n";

// 3. Create Shipment
echo "3. Creating shipment...\n";
$shipment = Shipment::create([
    'number' => 'SHP-' . date('Ymd') . '-' . rand(100, 999),
    'supplier_id' => $supplier->id,
    'date' => $today,
    'status' => 'open',
    'notes' => 'شحنة تجريبية - Simulation',
    'created_by' => $user->id ?? 1,
]);

// Add items
$totalCost = 0;
foreach ($products as $product) {
    $cartons = rand(50, 100);
    $weightPerUnit = round(rand(20, 30) + rand(0, 99) / 100, 2);
    $unitCost = round(rand(100, 200) + rand(0, 99) / 100, 2);
    $totalCost += $cartons * $unitCost;

    ShipmentItem::create([
        'shipment_id' => $shipment->id,
        'product_id' => $product->id,
        'cartons' => $cartons,
        'sold_cartons' => 0,
        'weight_per_unit' => $weightPerUnit,
        'unit_cost' => $unitCost,
    ]);

    echo "   - {$product->name}: {$cartons} cartons @ {$weightPerUnit}kg\n";
}

$shipment->update(['total_cost' => $totalCost]);
echo "   ✅ Shipment created (ID: {$shipment->id}, Total: {$totalCost})\n\n";

// 4. Create Invoice using FIFO
echo "4. Creating invoice...\n";
$fifoService = app(FifoAllocatorService::class);

$invoiceItems = [];
$subtotal = 0;
foreach ($products->take(2) as $product) {
    $cartons = rand(3, 10);
    $pricePerKg = round(rand(40, 60) + rand(0, 99) / 100, 2);

    // Allocate using FIFO
    $allocations = $fifoService->allocate($product->id, $cartons);

    if (!empty($allocations)) {
        foreach ($allocations as $allocation) {
            $actualWeight = $allocation['cartons'] * $allocation['weight_per_unit'] * (rand(95, 100) / 100);
            $itemSubtotal = $actualWeight * $pricePerKg;
            $subtotal += $itemSubtotal;

            $invoiceItems[] = [
                'product_id' => $product->id,
                'shipment_item_id' => $allocation['shipment_item_id'],
                'cartons' => $allocation['cartons'],
                'quantity' => round($actualWeight, 2),
                'unit_price' => $pricePerKg,
                'subtotal' => round($itemSubtotal, 2),
            ];

            echo "   - {$product->name}: {$allocation['cartons']} cartons, " . round($actualWeight, 2) . "kg\n";
        }
    }
}

$invoice = Invoice::create([
    'invoice_number' => 'INV-' . date('Ymd') . '-' . rand(100, 999),
    'customer_id' => $customer->id,
    'date' => $today,
    'subtotal' => round($subtotal, 2),
    'discount' => 0,
    'total' => round($subtotal, 2),
    'paid_amount' => 0,
    'balance' => round($subtotal, 2),
    'status' => 'active',
    'created_by' => $user->id ?? 1,
]);

foreach ($invoiceItems as $item) {
    InvoiceItem::create(array_merge($item, ['invoice_id' => $invoice->id]));
}

// Update customer balance
$customer->increment('balance', $invoice->total);

echo "   ✅ Invoice created (ID: {$invoice->id}, Total: {$invoice->total} AED)\n\n";

// 5. Create Collection
echo "5. Creating collection...\n";
$collectionAmount = round($invoice->total * 0.5, 2);

$collection = Collection::create([
    'receipt_number' => 'COL-' . date('Ymd') . '-' . rand(100, 999),
    'customer_id' => $customer->id,
    'date' => $today,
    'amount' => $collectionAmount,
    'payment_method' => 'cash',
    'distribution_method' => 'oldest_first',
    'notes' => 'تحصيل جزئي - Simulation',
    'created_by' => $user->id ?? 1,
]);

// Update balances
$customer->decrement('balance', $collectionAmount);
$invoice->increment('paid_amount', $collectionAmount);
$invoice->decrement('balance', $collectionAmount);

echo "   ✅ Collection created (ID: {$collection->id}, Amount: {$collectionAmount} AED)\n\n";

// 6. Create Expense
echo "6. Creating expense...\n";
$expense = Expense::create([
    'expense_number' => 'EXP-' . date('Ymd') . '-' . rand(100, 999),
    'date' => $today,
    'amount' => rand(50, 200),
    'type' => 'company',
    'payment_method' => 'cash',
    'description' => 'مصروفات نقل - Simulation',
    'created_by' => $user->id ?? 1,
]);

echo "   ✅ Expense created (ID: {$expense->id}, Amount: {$expense->amount} AED)\n\n";

// 7. Summary
echo "=== Daily Summary ===\n";
echo "Shipments: 1 (Total cost: {$totalCost} AED)\n";
echo "Invoices: 1 (Total: {$invoice->total} AED)\n";
echo "Collections: 1 (Amount: {$collectionAmount} AED)\n";
echo "Expenses: 1 (Amount: {$expense->amount} AED)\n";
echo "Customer balance: {$customer->fresh()->balance} AED\n\n";

// 8. Close Day
echo "8. Closing daily report...\n";
$dailyReport->update([
    'status' => 'closed',
    'closed_by' => $user->id ?? 1,
    'closed_at' => now(),
]);
echo "   ✅ Day closed!\n";

echo "\n=== Simulation Complete ===\n";
