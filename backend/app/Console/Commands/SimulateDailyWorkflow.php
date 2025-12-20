<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\User;
use App\Services\FifoAllocatorService;
use Illuminate\Console\Command;

class SimulateDailyWorkflow extends Command
{
    protected $signature = 'simulate:daily-workflow {--date= : Date to simulate (default: today)}';
    protected $description = 'Simulate a full daily business workflow';

    public function handle(FifoAllocatorService $fifoService): int
    {
        $date = $this->option('date') ?? now()->toDateString();

        $this->info("=== Daily Workflow Simulation ===");
        $this->info("Date: $date\n");

        // 1. Setup data
        $this->info("1. Setting up data...");
        $supplier = Supplier::first() ?? Supplier::factory()->create();
        $customer = Customer::first() ?? Customer::factory()->create();
        $products = Product::take(3)->get();
        $user = User::first();

        $this->line("   Supplier: {$supplier->name}");
        $this->line("   Customer: {$customer->name}");
        $this->line("   Products: " . $products->count() . "\n");

        // 2. Open Daily Report (skipped for now)
        $this->info("2. Opening daily report...");
        $this->warn("   ⚠️ Skipped\n");
        $dailyReport = null;

        // 3. Create Shipment
        $this->info("3. Creating shipment...");
        $shipment = Shipment::create([
            'number' => 'SHP-' . date('Ymd', strtotime($date)) . '-' . rand(100, 999),
            'supplier_id' => $supplier->id,
            'date' => $date,
            'status' => 'open',
            'notes' => 'شحنة تجريبية - Simulation',
            'created_by' => $user->id ?? 1,
        ]);

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

            $this->line("   - {$product->name}: {$cartons} cartons @ {$weightPerUnit}kg");
        }

        $shipment->update(['total_cost' => $totalCost]);
        $this->line("   ✅ Shipment created (ID: {$shipment->id})\n");

        // 4. Create Invoice
        $this->info("4. Creating invoice...");

        $invoiceItems = [];
        $subtotal = 0;
        foreach ($products->take(2) as $product) {
            $cartons = rand(3, 10);
            $pricePerKg = round(rand(40, 60) + rand(0, 99) / 100, 2);

            $allocations = $fifoService->allocate($product->id, $cartons);

            if (!empty($allocations)) {
                foreach ($allocations as $allocation) {
                    $actualWeight = $allocation['cartons'] * $allocation['weight_per_unit'] * (rand(95, 100) / 100);
                    $itemSubtotal = $actualWeight * $pricePerKg;
                    $subtotal += $itemSubtotal;

                    // Update sold_cartons in shipment item (this was missing!)
                    ShipmentItem::where('id', $allocation['shipment_item_id'])
                        ->increment('sold_cartons', $allocation['cartons']);

                    $invoiceItems[] = [
                        'product_id' => $product->id,
                        'shipment_item_id' => $allocation['shipment_item_id'],
                        'cartons' => $allocation['cartons'],
                        'quantity' => round($actualWeight, 2),
                        'unit_price' => $pricePerKg,
                        'subtotal' => round($itemSubtotal, 2),
                    ];

                    $this->line("   - {$product->name}: {$allocation['cartons']} cartons");
                }
            }
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . date('Ymd', strtotime($date)) . '-' . rand(100, 999),
            'customer_id' => $customer->id,
            'date' => $date,
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

        $this->line("   DEBUG: Customer balance BEFORE increment: {$customer->fresh()->balance}");
        $customer->increment('balance', $invoice->total);
        $this->line("   DEBUG: Customer balance AFTER increment (+{$invoice->total}): {$customer->fresh()->balance}");
        $this->line("   ✅ Invoice created (ID: {$invoice->id}, Total: {$invoice->total} AED)\n");

        // 5. Create Collection
        $this->info("5. Creating collection...");
        $collectionAmount = round($invoice->total * 0.5, 2);

        $collection = Collection::create([
            'receipt_number' => 'COL-' . date('Ymd', strtotime($date)) . '-' . rand(100, 999),
            'customer_id' => $customer->id,
            'date' => $date,
            'amount' => $collectionAmount,
            'payment_method' => 'cash',
            'distribution_method' => 'oldest_first',
            'notes' => 'تحصيل جزئي - Simulation',
            'created_by' => $user->id ?? 1,
        ]);

        // NOTE: Customer balance and Invoice updates are done by CollectionObserver and CollectionAllocationObserver
        // No manual update needed here

        $this->line("   ✅ Collection created (Amount: {$collectionAmount} AED)\n");

        // 6. Create Expense
        $this->info("6. Creating expense...");
        $expense = Expense::create([
            'expense_number' => 'EXP-' . date('Ymd', strtotime($date)) . '-' . rand(100, 999),
            'date' => $date,
            'amount' => rand(50, 200),
            'type' => 'company',
            'category' => 'transport',
            'payment_method' => 'cash',
            'description' => 'مصروفات نقل - Simulation',
            'created_by' => $user->id ?? 1,
        ]);

        $this->line("   ✅ Expense created (Amount: {$expense->amount} AED)\n");

        // 7. Summary
        $this->newLine();
        $this->table(
            ['Item', 'Value'],
            [
                ['Shipment', "ID: {$shipment->id}, Cost: {$totalCost} AED"],
                ['Invoice', "ID: {$invoice->id}, Total: {$invoice->total} AED"],
                ['Collection', "Amount: {$collectionAmount} AED (50%)"],
                ['Expense', "Amount: {$expense->amount} AED"],
                ['Customer Balance', "{$customer->fresh()->balance} AED"],
            ]
        );

        // 8. Close Day
        $this->info("\n8. Closing daily report...");
        if ($dailyReport) {
            $dailyReport->update([
                'status' => 'closed',
                'closed_by' => $user->id ?? 1,
                'closed_at' => now(),
            ]);
            $this->line("   ✅ Day closed!");
        } else {
            $this->warn("   ⚠️ No daily report to close.");
        }

        $this->info("\n=== Simulation Complete ===");

        return Command::SUCCESS;
    }
}
