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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * RealisticDemoSeeder - بيانات واقعية تحاكي العمل الفعلي
 * 
 * التدفق:
 * 1. 9 أصناف فواكه حقيقية
 * 2. شحنة واحدة مفتوحة
 * 3. 8 أيام من العمليات (فواتير + تحصيلات + مصروفات)
 * 4. الفواتير مع Items مرتبطة بـ ShipmentItems (FIFO)
 */
class RealisticDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 بدء إنشاء البيانات الواقعية...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@azafco.com'],
            ['name' => 'مدير النظام', 'password' => Hash::make('password'), 'is_admin' => true]
        );
        $this->command->info('✅ Admin User');

        // 2. Suppliers (3)
        $supplier = Supplier::firstOrCreate(['code' => 'SUP-001'], ['name' => 'شركة الفواكه الطازجة', 'phone' => '0512345678', 'is_active' => true, 'balance' => 0]);
        Supplier::firstOrCreate(['code' => 'SUP-002'], ['name' => 'مؤسسة الحصاد', 'phone' => '0523456789', 'is_active' => true, 'balance' => 0]);
        Supplier::firstOrCreate(['code' => 'SUP-003'], ['name' => 'شركة الوادي الأخضر', 'phone' => '0534567890', 'is_active' => true, 'balance' => 0]);
        $this->command->info('✅ 3 Suppliers');

        // 3. Products (9 فواكه)
        $productData = [
            ['name' => 'تفاح أحمر', 'name_en' => 'Red Apple'],
            ['name' => 'موز', 'name_en' => 'Banana'],
            ['name' => 'برتقال', 'name_en' => 'Orange'],
            ['name' => 'عنب أحمر', 'name_en' => 'Red Grape'],
            ['name' => 'مانجو', 'name_en' => 'Mango'],
            ['name' => 'فراولة', 'name_en' => 'Strawberry'],
            ['name' => 'كيوي', 'name_en' => 'Kiwi'],
            ['name' => 'رمان', 'name_en' => 'Pomegranate'],
            ['name' => 'بطيخ', 'name_en' => 'Watermelon'],
        ];
        $products = collect();
        foreach ($productData as $p) {
            $products->push(Product::create(array_merge($p, ['category' => 'فواكه', 'is_active' => true])));
        }
        $this->command->info('✅ 9 Products');

        // 4. Customers (15)
        $customerNames = [
            'سوبرماركت الأمل',
            'بقالة النور',
            'ميني ماركت السلام',
            'سوبرماركت الرحمة',
            'بقالة البركة',
            'ميني ماركت الخير',
            'سوبرماركت التوفيق',
            'بقالة الإحسان',
            'سوبرماركت الفلاح',
            'بقالة النجاح',
            'ميني ماركت الهناء',
            'سوبرماركت الوفاء',
            'بقالة الصفاء',
            'ميني ماركت الكرم',
            'سوبرماركت الجود'
        ];
        $customers = collect();
        foreach ($customerNames as $i => $name) {
            $customers->push(Customer::create([
                'code' => 'CUS-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'name' => $name,
                'phone' => '05' . str_pad($i + 1, 8, '0', STR_PAD_LEFT),
                'balance' => 0,
                'is_active' => true,
            ]));
        }
        $this->command->info('✅ 15 Customers');

        // 5. Shipment with Items (كميات كبيرة للبيع)
        $shipment = Shipment::create([
            'number' => 'SHP-2024-001',
            'supplier_id' => $supplier->id,
            'date' => now()->subDays(10),
            'status' => 'open',
            'total_cost' => 0,
            'created_by' => $admin->id,
        ]);

        $shipmentItems = collect();
        $totalCost = 0;
        $costs = [8, 6, 5, 15, 12, 20, 18, 10, 3]; // تكلفة كل صنف

        foreach ($products as $i => $product) {
            $qty = rand(800, 1500); // كميات كبيرة
            $cost = $costs[$i];
            $si = ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'product_id' => $product->id,
                'initial_quantity' => $qty,
                'remaining_quantity' => $qty,
                'sold_quantity' => 0,
                'unit_cost' => $cost,
                'total_cost' => $qty * $cost,
            ]);
            $shipmentItems->push($si);
            $totalCost += $qty * $cost;
        }
        $shipment->update(['total_cost' => $totalCost]);
        $this->command->info('✅ 1 Shipment with 9 Items');

        // 6. 8 أيام من العمليات
        $invoiceCounter = 0;
        $collectionCounter = 0;
        $expenseCounter = 0;

        for ($d = 7; $d >= 0; $d--) {
            $date = now()->subDays($d)->toDateString();

            // Daily Report
            DailyReport::create([
                'date' => $date,
                'status' => $d > 0 ? 'closed' : 'open',
                'cashbox_opening' => rand(5000, 15000),
                'bank_opening' => rand(50000, 100000),
                'opened_by' => $admin->id,
            ]);

            // 5-8 Invoices per day
            $dayInvoices = rand(5, 8);
            for ($i = 0; $i < $dayInvoices; $i++) {
                $invoiceCounter++;
                $customer = $customers->random();

                $invoice = Invoice::create([
                    'invoice_number' => 'INV-' . str_pad($invoiceCounter, 5, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'date' => $date,
                    'type' => 'sale',
                    'status' => 'active',
                    'subtotal' => 0,
                    'discount' => rand(0, 15),
                    'total' => 0,
                    'paid_amount' => 0,
                    'balance' => 0,
                    'created_by' => $admin->id,
                ]);

                // 2-4 Items per invoice
                $subtotal = 0;
                $itemCount = rand(2, 4);
                $usedProducts = [];

                for ($j = 0; $j < $itemCount; $j++) {
                    // اختر shipment item عشوائي بمخزون متاح
                    $availableItems = $shipmentItems->filter(function ($si) use ($usedProducts) {
                        return $si->remaining_quantity > 20 && !in_array($si->product_id, $usedProducts);
                    });

                    if ($availableItems->isEmpty())
                        break;

                    $si = $availableItems->random();
                    $usedProducts[] = $si->product_id;

                    $qty = rand(10, min(50, (int) $si->remaining_quantity));
                    $price = $si->unit_cost + rand(3, 8); // ربح 3-8 جنيه
                    $itemSubtotal = $qty * $price;

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $si->product_id,
                        'shipment_item_id' => $si->id,
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $itemSubtotal,
                    ]);

                    // FIFO: خصم من المخزون
                    $si->decrement('remaining_quantity', $qty);
                    $si->increment('sold_quantity', $qty);

                    $subtotal += $itemSubtotal;
                }

                // تحديث إجمالي الفاتورة
                $total = $subtotal - $invoice->discount;
                $invoice->update([
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'balance' => $total,
                ]);

                // زيادة رصيد العميل
                $customer->increment('balance', $total);
            }

            // 3-5 Collections per day
            $dayCollections = rand(3, 5);
            for ($i = 0; $i < $dayCollections; $i++) {
                $customer = Customer::where('balance', '>', 100)->inRandomOrder()->first();
                if (!$customer)
                    continue;

                $collectionCounter++;
                $amount = min(rand(200, 800), (int) $customer->balance);

                Collection::create([
                    'receipt_number' => 'REC-' . str_pad($collectionCounter, 5, '0', STR_PAD_LEFT),
                    'customer_id' => $customer->id,
                    'date' => $date,
                    'amount' => $amount,
                    'payment_method' => ['cash', 'bank_transfer'][rand(0, 1)],
                    'status' => 'confirmed',
                    'distribution_method' => 'fifo',
                    'created_by' => $admin->id,
                ]);

                $customer->decrement('balance', $amount);
            }

            // 2-3 Expenses per day
            $categories = ['عمال', 'نقل', 'كهرباء', 'إيجار', 'صيانة'];
            $dayExpenses = rand(2, 3);
            for ($i = 0; $i < $dayExpenses; $i++) {
                $expenseCounter++;
                Expense::create([
                    'expense_number' => 'EXP-' . str_pad($expenseCounter, 5, '0', STR_PAD_LEFT),
                    'type' => 'company',
                    'date' => $date,
                    'category' => $categories[array_rand($categories)],
                    'amount' => rand(100, 500),
                    'description' => 'مصروف يومي',
                    'payment_method' => 'cash',
                    'created_by' => $admin->id,
                ]);
            }
        }
        $this->command->info('✅ 8 Days of Operations');

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('🎉 تم إنشاء البيانات الواقعية بنجاح!');

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
