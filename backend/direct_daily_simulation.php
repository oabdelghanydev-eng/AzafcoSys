<?php
// Full Daily Workflow Simulation via Laravel Direct
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\{User, Supplier, Customer, Product, Shipment, ShipmentItem, Invoice, InvoiceItem, Collection, Expense, Account, DailyReport};
use App\Services\FifoAllocatorService;

$today = '2025-12-20';
$user = User::first();

echo "=== DAILY WORKFLOW SIMULATION ===\n";
echo "Date: $today\n\n";

// STEP 1: Create Accounts
echo "1. Creating accounts...\n";
$cashbox = Account::firstOrCreate(['type' => 'cashbox'], ['name' => 'Main Cashbox', 'balance' => 100000, 'is_active' => true]);
$bank = Account::firstOrCreate(['type' => 'bank'], ['name' => 'Bank Account', 'balance' => 500000, 'is_active' => true]);
echo "   Cashbox: {$cashbox->balance} AED\n";
echo "   Bank: {$bank->balance} AED\n";

// STEP 2: Create Supplier
echo "\n2. Creating supplier...\n";
$ts = time();
$supplier = Supplier::create(['code' => 'SUP-' . $ts, 'name' => 'Fresh Fish Co.', 'name_en' => 'Fresh Fish Co.', 'phone' => '0501234567']);
echo "   Supplier: {$supplier->name} (ID: {$supplier->id})\n";

// STEP 3: Create 3 Customers
echo "\n3. Creating 3 customers...\n";
$c1 = Customer::create(['code' => 'C1-' . $ts, 'name' => 'Golden Restaurant', 'phone' => '0551111111']);
$c2 = Customer::create(['code' => 'C2-' . $ts, 'name' => 'Al Khair Market', 'phone' => '0552222222']);
$c3 = Customer::create(['code' => 'C3-' . $ts, 'name' => 'Sea Hotel', 'phone' => '0553333333']);
echo "   Customer 1: {$c1->name} (ID: {$c1->id})\n";
echo "   Customer 2: {$c2->name} (ID: {$c2->id})\n";
echo "   Customer 3: {$c3->name} (ID: {$c3->id})\n";

// STEP 4: Get Products
echo "\n4. Getting products...\n";
$products = Product::take(5)->get();
foreach ($products as $p)
    echo "   - {$p->name} (ID: {$p->id})\n";

// STEP 5: Open Daily Report
echo "\n5. Opening daily report...\n";
try {
    $dr = DailyReport::create(['date' => $today, 'status' => 'open', 'opened_by' => $user->id, 'created_by' => $user->id]);
    echo "   Day opened (ID: {$dr->id})\n";
} catch (Exception $e) {
    echo "   Skipped: " . $e->getMessage() . "\n";
    $dr = null;
}

// STEP 6: Shipment 1
echo "\n6. Creating shipment 1...\n";
$ship1 = Shipment::create(['number' => 'SHP-20251220-001', 'supplier_id' => $supplier->id, 'date' => $today, 'status' => 'open', 'created_by' => $user->id]);
ShipmentItem::create(['shipment_id' => $ship1->id, 'product_id' => $products[0]->id, 'cartons' => 100, 'sold_cartons' => 0, 'weight_per_unit' => 25.5, 'unit_cost' => 150]);
ShipmentItem::create(['shipment_id' => $ship1->id, 'product_id' => $products[1]->id, 'cartons' => 80, 'sold_cartons' => 0, 'weight_per_unit' => 22.0, 'unit_cost' => 140]);
ShipmentItem::create(['shipment_id' => $ship1->id, 'product_id' => $products[2]->id, 'cartons' => 60, 'sold_cartons' => 0, 'weight_per_unit' => 28.0, 'unit_cost' => 160]);
echo "   Shipment 1 created: 3 items, 240 cartons\n";

// STEP 7: Shipment 2
echo "\n7. Creating shipment 2...\n";
$ship2 = Shipment::create(['number' => 'SHP-20251220-002', 'supplier_id' => $supplier->id, 'date' => $today, 'status' => 'open', 'created_by' => $user->id]);
ShipmentItem::create(['shipment_id' => $ship2->id, 'product_id' => $products[3]->id, 'cartons' => 50, 'sold_cartons' => 0, 'weight_per_unit' => 20.0, 'unit_cost' => 130]);
ShipmentItem::create(['shipment_id' => $ship2->id, 'product_id' => $products[4]->id, 'cartons' => 70, 'sold_cartons' => 0, 'weight_per_unit' => 24.0, 'unit_cost' => 145]);
echo "   Shipment 2 created: 2 items, 120 cartons\n";

// STEP 8: Create Invoices with FIFO
echo "\n8. Creating invoices with FIFO...\n";
$fifo = app(FifoAllocatorService::class);
$totalSales = 0;

// Invoice 1: Customer 1
$inv1 = Invoice::create(['invoice_number' => 'INV-20251220-001', 'customer_id' => $c1->id, 'date' => $today, 'subtotal' => 0, 'discount' => 0, 'total' => 0, 'paid_amount' => 0, 'balance' => 0, 'status' => 'active', 'created_by' => $user->id]);
$items1 = $fifo->allocateAndCreate($inv1->id, $products[0]->id, 10, 240, 55);
$items1 = $items1->merge($fifo->allocateAndCreate($inv1->id, $products[1]->id, 5, 105, 52));
$total1 = $items1->sum('subtotal');
$inv1->update(['subtotal' => $total1, 'total' => $total1, 'balance' => $total1]);
$c1->increment('balance', $total1);
$totalSales += $total1;
echo "   Invoice 1: " . number_format($total1, 2) . " AED\n";

// Invoice 2: Customer 2
$inv2 = Invoice::create(['invoice_number' => 'INV-20251220-002', 'customer_id' => $c2->id, 'date' => $today, 'subtotal' => 0, 'discount' => 0, 'total' => 0, 'paid_amount' => 0, 'balance' => 0, 'status' => 'active', 'created_by' => $user->id]);
$items2 = $fifo->allocateAndCreate($inv2->id, $products[2]->id, 8, 220, 58);
$total2 = $items2->sum('subtotal');
$inv2->update(['subtotal' => $total2, 'total' => $total2, 'balance' => $total2]);
$c2->increment('balance', $total2);
$totalSales += $total2;
echo "   Invoice 2: " . number_format($total2, 2) . " AED\n";

// Invoice 3: Customer 3
$inv3 = Invoice::create(['invoice_number' => 'INV-20251220-003', 'customer_id' => $c3->id, 'date' => $today, 'subtotal' => 0, 'discount' => 0, 'total' => 0, 'paid_amount' => 0, 'balance' => 0, 'status' => 'active', 'created_by' => $user->id]);
$items3 = $fifo->allocateAndCreate($inv3->id, $products[0]->id, 15, 360, 54);
$items3 = $items3->merge($fifo->allocateAndCreate($inv3->id, $products[1]->id, 10, 215, 51));
$total3 = $items3->sum('subtotal');
$inv3->update(['subtotal' => $total3, 'total' => $total3, 'balance' => $total3]);
$c3->increment('balance', $total3);
$totalSales += $total3;
echo "   Invoice 3: " . number_format($total3, 2) . " AED\n";

// Invoice 4: Customer 1 again
$inv4 = Invoice::create(['invoice_number' => 'INV-20251220-004', 'customer_id' => $c1->id, 'date' => $today, 'subtotal' => 0, 'discount' => 0, 'total' => 0, 'paid_amount' => 0, 'balance' => 0, 'status' => 'active', 'created_by' => $user->id]);
$items4 = $fifo->allocateAndCreate($inv4->id, $products[3]->id, 6, 115, 48);
$total4 = $items4->sum('subtotal');
$inv4->update(['subtotal' => $total4, 'total' => $total4, 'balance' => $total4]);
$c1->increment('balance', $total4);
$totalSales += $total4;
echo "   Invoice 4: " . number_format($total4, 2) . " AED\n";
echo "   TOTAL SALES: " . number_format($totalSales, 2) . " AED\n";

// STEP 9: Create Collections
echo "\n9. Creating collections...\n";
$totalCollections = 0;
$cols = [
    ['customer' => $c1, 'amount' => 10000, 'method' => 'cash'],
    ['customer' => $c2, 'amount' => 5000, 'method' => 'bank'],
    ['customer' => $c3, 'amount' => 15000, 'method' => 'cash'],
    ['customer' => $c1, 'amount' => 3000, 'method' => 'cash'],
];
foreach ($cols as $i => $col) {
    $coll = Collection::create([
        'receipt_number' => 'COL-20251220-00' . ($i + 1),
        'customer_id' => $col['customer']->id,
        'date' => $today,
        'amount' => $col['amount'],
        'payment_method' => $col['method'],
        'distribution_method' => 'oldest_first',
        'created_by' => $user->id,
    ]);
    $col['customer']->decrement('balance', $col['amount']);
    $totalCollections += $col['amount'];
    echo "   Collection " . ($i + 1) . ": " . number_format($col['amount']) . " AED ({$col['method']})\n";
}
echo "   TOTAL COLLECTIONS: " . number_format($totalCollections) . " AED\n";

// STEP 10: Create Expenses
echo "\n10. Creating expenses...\n";
$totalExpenses = 0;
$exps = [
    ['amount' => 500, 'type' => 'company', 'category' => 'transport', 'desc' => 'Transport costs', 'method' => 'cash'],
    ['amount' => 200, 'type' => 'company', 'category' => 'utilities', 'desc' => 'Electricity bill', 'method' => 'bank'],
    ['amount' => 1000, 'type' => 'supplier', 'category' => 'payment', 'desc' => 'Supplier payment', 'method' => 'cash', 'supplier_id' => $supplier->id],
    ['amount' => 150, 'type' => 'company', 'category' => 'office', 'desc' => 'Office supplies', 'method' => 'cash'],
];
foreach ($exps as $i => $exp) {
    $expense = Expense::create([
        'expense_number' => 'EXP-20251220-00' . ($i + 1),
        'date' => $today,
        'amount' => $exp['amount'],
        'type' => $exp['type'],
        'category' => $exp['category'],
        'payment_method' => $exp['method'],
        'description' => $exp['desc'],
        'supplier_id' => $exp['supplier_id'] ?? null,
        'created_by' => $user->id,
    ]);
    $totalExpenses += $exp['amount'];
    echo "   Expense " . ($i + 1) . ": " . number_format($exp['amount']) . " AED - {$exp['desc']}\n";
}
echo "   TOTAL EXPENSES: " . number_format($totalExpenses) . " AED\n";

// STEP 11: Close Day
echo "\n11. Closing daily report...\n";
if ($dr) {
    $dr->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $user->id, 'total_sales' => $totalSales, 'total_collections' => $totalCollections, 'total_expenses' => $totalExpenses]);
    echo "   Day closed!\n";
} else {
    echo "   No daily report to close\n";
}

// STEP 12: Verify
echo "\n12. Verification...\n";
$totalCartons = ShipmentItem::sum('cartons');
$soldCartons = ShipmentItem::sum('sold_cartons');
echo "   Total Cartons: $totalCartons\n";
echo "   Sold Cartons: $soldCartons\n";
echo "   Remaining: " . ($totalCartons - $soldCartons) . "\n";

echo "\n=== SIMULATION COMPLETE ===\n";
echo "Run: php artisan report:daily $today\n";
