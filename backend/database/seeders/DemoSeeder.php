<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DemoSeeder - Senior 2025 Approach
 * Uses existing Factories with proper date linking + Invoice Items
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 بدء إنشاء البيانات الوهمية...');

        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@azafco.com'],
            ['name' => 'مدير النظام', 'password' => Hash::make('password'), 'is_admin' => true]
        );
        $this->command->info('✅ Admin User');

        // 2. Suppliers using Factory
        Supplier::factory()->count(10)->create();
        $this->command->info('✅ 10 Suppliers');

        // 3. Products using Factory
        Product::factory()->count(9)->create();
        $this->command->info('✅ 9 Products');

        // 4. Customers using Factory
        $customers = Customer::factory()->count(15)->create();
        $this->command->info('✅ 15 Customers');

        // 5. Shipments with Items using Factory
        $shipments = Shipment::factory()
            ->count(5)
            ->has(ShipmentItem::factory()->count(9), 'items')
            ->create(['created_by' => $admin->id]);
        $this->command->info('✅ 5 Shipments with Items');

        // Get all shipment items for FIFO linking
        $shipmentItems = ShipmentItem::all();

        // 6. Daily Reports + linked transactions with Invoice Items
        for ($d = 7; $d >= 0; $d--) {
            $date = now()->subDays($d)->toDateString();

            // Create Daily Report
            DailyReport::factory()->create([
                'date' => $date,
                'status' => $d > 0 ? 'closed' : 'open',
                'opened_by' => $admin->id,
            ]);

            // Create 5-8 Invoices for this date with Items
            $invoiceCount = rand(5, 8);
            for ($i = 0; $i < $invoiceCount; $i++) {
                $customer = $customers->random();

                $invoice = Invoice::create([
                    'invoice_number' => 'INV-' . date('Ymd', strtotime($date)) . '-' . str_pad($i + 1 + ($d * 10), 4, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'date' => $date,
                    'type' => 'sale',
                    'status' => 'active',
                    'subtotal' => 0,
                    'discount' => rand(0, 10),
                    'total' => 0,
                    'paid_amount' => 0,
                    'balance' => 0,
                    'created_by' => $admin->id,
                ]);

                // Add 2-4 Invoice Items linked to ShipmentItems
                $subtotal = 0;
                $itemCount = rand(2, 4);
                $usedProducts = [];

                for ($j = 0; $j < $itemCount; $j++) {
                    $availableItems = $shipmentItems->filter(function ($si) use ($usedProducts) {
                        return $si->remaining_quantity > 10 && !in_array($si->product_id, $usedProducts);
                    });

                    if ($availableItems->isEmpty())
                        break;

                    $si = $availableItems->random();
                    $usedProducts[] = $si->product_id;

                    $qty = rand(5, min(30, (int) $si->remaining_quantity));
                    $price = ($si->unit_cost ?? 10) + rand(3, 8);
                    $itemSubtotal = $qty * $price;

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $si->product_id,
                        'shipment_item_id' => $si->id,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $itemSubtotal,
                    ]);

                    // FIFO deduction
                    $si->decrement('remaining_quantity', $qty);
                    $si->increment('sold_quantity', $qty);

                    $subtotal += $itemSubtotal;
                }

                // Update invoice totals
                $total = $subtotal - $invoice->discount;
                $invoice->update([
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'balance' => $total,
                ]);

                $customer->increment('balance', $total);
            }

            // Create 3-5 Collections for this date
            for ($i = 0; $i < rand(3, 5); $i++) {
                $customer = Customer::where('balance', '>', 50)->inRandomOrder()->first();
                if (!$customer)
                    continue;

                $amount = min(rand(100, 500), (int) $customer->balance);

                Collection::factory()->create([
                    'date' => $date,
                    'customer_id' => $customer->id,
                    'amount' => $amount,
                    'created_by' => $admin->id,
                    'status' => 'confirmed',
                ]);

                $customer->decrement('balance', $amount);
            }

            // Create 2-3 Expenses for this date
            Expense::factory()
                ->count(rand(2, 3))
                ->create([
                    'date' => $date,
                    'created_by' => $admin->id,
                ]);
        }
        $this->command->info('✅ 8 Days of Data (Reports, Invoices with Items, Collections, Expenses)');

        $this->command->info('🎉 تم إنشاء البيانات الوهمية بنجاح!');

        // Summary
        $this->command->table(['Entity', 'Count'], [
            ['Products', Product::count()],
            ['Customers', Customer::count()],
            ['Daily Reports', DailyReport::count()],
            ['Invoices', Invoice::count()],
            ['Invoice Items', InvoiceItem::count()],
            ['Collections', Collection::count()],
            ['Expenses', Expense::count()],
        ]);
    }
}
