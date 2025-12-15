<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Account;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Collection;
use App\Models\Expense;
use App\Models\Transfer;

/**
 * Demo Data Seeder - 2025 Best Practices
 * 
 * Creates realistic sample data for:
 * - PDF Report testing
 * - API endpoint testing
 * - Development environment
 * 
 * Usage: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only run in non-production environments
        if (app()->environment('production')) {
            $this->command->error('Cannot run DemoSeeder in production!');
            return;
        }

        $this->command->info('🌱 Starting Demo Data Seeding...');

        // 1. Ensure accounts exist
        $this->seedAccounts();

        // 2. Create or get admin user
        $user = $this->seedAdminUser();

        // 3. Create products if empty
        $products = $this->seedProducts();

        // 4. Create customers
        $customers = $this->seedCustomers();

        // 5. Create supplier and shipment
        $shipment = $this->seedShipmentWithItems($user, $products);

        // 6. Create invoices with items
        $this->seedInvoicesWithItems($user, $customers, $shipment);

        // 7. Create collections
        $this->seedCollections($user, $customers);

        // 8. Create expenses
        $this->seedExpenses($user, $shipment);

        // 9. Create transfers
        $this->seedTransfers($user);

        // 10. Create daily report
        $this->seedDailyReport($user);

        $this->command->newLine();
        $this->command->info('✅ Demo Data Seeding Complete!');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Products', Product::count()],
                ['Customers', Customer::count()],
                ['Suppliers', Supplier::count()],
                ['Shipments', Shipment::count()],
                ['Invoices', Invoice::count()],
                ['Collections', Collection::count()],
                ['Expenses', Expense::count()],
                ['Transfers', Transfer::count()],
            ]
        );
    }

    private function seedAccounts(): void
    {
        $this->command->info('  → Seeding Accounts...');

        if (!Account::where('type', 'cashbox')->exists()) {
            Account::create([
                'type' => 'cashbox',
                'name' => 'الخزنة الرئيسية',
                'balance' => 10000,
                'is_active' => true,
            ]);
        }

        if (!Account::where('type', 'bank')->exists()) {
            Account::create([
                'type' => 'bank',
                'name' => 'البنك الرئيسي',
                'balance' => 50000,
                'is_active' => true,
            ]);
        }
    }

    private function seedAdminUser(): User
    {
        $this->command->info('  → Seeding Admin User...');

        return User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'is_admin' => true,
                'is_locked' => false,
            ]
        );
    }

    private function seedProducts(): \Illuminate\Database\Eloquent\Collection
    {
        $this->command->info('  → Seeding Products...');

        if (Product::count() >= 5) {
            return Product::all();
        }

        $products = [
            ['name' => 'سمك بلطي A', 'name_en' => 'Tilapia A', 'category' => 'fish', 'is_active' => true],
            ['name' => 'سمك بلطي B', 'name_en' => 'Tilapia B', 'category' => 'fish', 'is_active' => true],
            ['name' => 'سمك قاروص', 'name_en' => 'Seabass', 'category' => 'fish', 'is_active' => true],
            ['name' => 'جمبري كبير', 'name_en' => 'Large Shrimp', 'category' => 'seafood', 'is_active' => true],
            ['name' => 'جمبري صغير', 'name_en' => 'Small Shrimp', 'category' => 'seafood', 'is_active' => true],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['name' => $p['name']], $p);
        }

        return Product::all();
    }

    private function seedCustomers(): \Illuminate\Database\Eloquent\Collection
    {
        $this->command->info('  → Seeding Customers...');

        if (Customer::count() >= 3) {
            return Customer::all();
        }

        $customers = [
            ['code' => 'CUS-00001', 'name' => 'محمد أحمد', 'phone' => '01001234567', 'balance' => 0, 'is_active' => true],
            ['code' => 'CUS-00002', 'name' => 'سوبر ماركت النور', 'phone' => '01112345678', 'balance' => 500, 'is_active' => true],
            ['code' => 'CUS-00003', 'name' => 'مطعم السمك الذهبي', 'phone' => '01223456789', 'balance' => 1200, 'is_active' => true],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(['code' => $c['code']], $c);
        }

        return Customer::all();
    }

    private function seedShipmentWithItems(User $user, $products): Shipment
    {
        $this->command->info('  → Seeding Shipment with Items...');

        // Create supplier
        $supplier = Supplier::firstOrCreate(
            ['code' => 'SUP-00001'],
            [
                'name' => 'مورد الأسماك الرئيسي',
                'phone' => '01099887766',
                'balance' => 0,
                'is_active' => true,
            ]
        );

        // Create shipment
        $shipment = Shipment::firstOrCreate(
            ['number' => 'SHP-DEMO-001'],
            [
                'supplier_id' => $supplier->id,
                'date' => now()->subDays(3),
                'status' => 'open',
                'total_cost' => 15000,
                'notes' => 'Demo shipment for testing',
                'created_by' => $user->id,
            ]
        );

        // Create shipment items for each product
        foreach ($products->take(3) as $index => $product) {
            ShipmentItem::firstOrCreate(
                [
                    'shipment_id' => $shipment->id,
                    'product_id' => $product->id,
                ],
                [
                    'weight_per_unit' => fake()->randomFloat(2, 0.5, 2),
                    'weight_label' => 'A' . ($index + 1),
                    'cartons' => fake()->numberBetween(10, 50),
                    'initial_quantity' => 200,
                    'remaining_quantity' => 150, // Some sold
                    'sold_quantity' => 50,
                    'unit_cost' => fake()->randomFloat(2, 30, 80),
                    'total_cost' => 200 * fake()->randomFloat(2, 30, 80),
                ]
            );
        }

        return $shipment;
    }

    private function seedInvoicesWithItems(User $user, $customers, Shipment $shipment): void
    {
        $this->command->info('  → Seeding Invoices with Items...');

        $shipmentItems = $shipment->items;
        $today = now()->toDateString();

        foreach ($customers->take(2) as $index => $customer) {
            $invoiceNumber = 'INV-DEMO-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            $invoice = Invoice::firstOrCreate(
                ['invoice_number' => $invoiceNumber],
                [
                    'customer_id' => $customer->id,
                    'date' => $today,
                    'subtotal' => 0,
                    'discount' => 0,
                    'total' => 0,
                    'paid_amount' => 0,
                    'balance' => 0,
                    'type' => 'sale',
                    'status' => 'active',
                    'created_by' => $user->id,
                ]
            );

            // Add invoice items
            $total = 0;
            foreach ($shipmentItems->take(2) as $shipmentItem) {
                $quantity = fake()->randomFloat(2, 10, 30);
                $unitPrice = fake()->randomFloat(2, 50, 120);
                $subtotal = $quantity * $unitPrice;
                $total += $subtotal;

                DB::table('invoice_items')->insertOrIgnore([
                    'invoice_id' => $invoice->id,
                    'product_id' => $shipmentItem->product_id,
                    'shipment_item_id' => $shipmentItem->id,
                    'cartons' => fake()->numberBetween(1, 5),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update invoice totals
            $invoice->update([
                'subtotal' => $total,
                'total' => $total,
                'balance' => $total,
            ]);
        }
    }

    private function seedCollections(User $user, $customers): void
    {
        $this->command->info('  → Seeding Collections...');

        $today = now()->toDateString();

        foreach ($customers->take(2) as $index => $customer) {
            $receiptNumber = 'REC-DEMO-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

            Collection::firstOrCreate(
                ['receipt_number' => $receiptNumber],
                [
                    'customer_id' => $customer->id,
                    'date' => $today,
                    'amount' => fake()->randomFloat(2, 200, 800),
                    'payment_method' => $index % 2 == 0 ? 'cash' : 'bank',
                    'distribution_method' => 'oldest_first',
                    'status' => 'confirmed',
                    'created_by' => $user->id,
                ]
            );
        }
    }

    private function seedExpenses(User $user, Shipment $shipment): void
    {
        $this->command->info('  → Seeding Expenses...');

        $today = now()->toDateString();

        // Company expense
        Expense::firstOrCreate(
            ['expense_number' => 'EXP-DEMO-001'],
            [
                'type' => 'company',
                'supplier_id' => null,
                'date' => $today,
                'amount' => 150,
                'payment_method' => 'cash',
                'category' => 'utilities',
                'description' => 'Demo company expense',
                'created_by' => $user->id,
            ]
        );

        // Supplier expense
        Expense::firstOrCreate(
            ['expense_number' => 'EXP-DEMO-002'],
            [
                'type' => 'supplier',
                'supplier_id' => $shipment->supplier_id,
                'date' => $today,
                'amount' => 200,
                'payment_method' => 'cash',
                'category' => 'transport',
                'description' => 'Demo supplier transport',
                'created_by' => $user->id,
            ]
        );
    }

    private function seedTransfers(User $user): void
    {
        $this->command->info('  → Seeding Transfers...');

        $today = now()->toDateString();

        if (Transfer::count() == 0) {
            $cashbox = Account::where('type', 'cashbox')->first();
            $bank = Account::where('type', 'bank')->first();

            Transfer::create([
                'from_account_id' => $cashbox->id,
                'to_account_id' => $bank->id,
                'amount' => 2000,
                'date' => $today,
                'notes' => 'Demo transfer',
                'created_by' => $user->id,
            ]);
        }
    }

    private function seedDailyReport(User $user): void
    {
        $this->command->info('  → Seeding Daily Report...');

        $today = now()->toDateString();

        DB::table('daily_reports')->insertOrIgnore([
            'date' => $today,
            'total_sales' => Invoice::whereDate('date', $today)->sum('total'),
            'total_collections' => Collection::whereDate('date', $today)->sum('amount'),
            'total_expenses' => Expense::whereDate('date', $today)->sum('amount'),
            'cash_balance' => Account::where('type', 'cashbox')->first()?->balance ?? 0,
            'bank_balance' => Account::where('type', 'bank')->first()?->balance ?? 0,
            'invoices_count' => Invoice::whereDate('date', $today)->count(),
            'collections_count' => Collection::whereDate('date', $today)->count(),
            'expenses_count' => Expense::whereDate('date', $today)->count(),
            'notes' => 'Auto-generated demo daily report',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
