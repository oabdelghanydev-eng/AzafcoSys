<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // 10 Fixed Products (الأصناف الثابتة)
        $products = [
            ['name' => 'بلطي', 'name_en' => 'TILAPIA', 'category' => 'سمك'],
            ['name' => 'مبروكة', 'name_en' => 'ROHO', 'category' => 'سمك'],
            ['name' => 'بني', 'name_en' => 'KATLA', 'category' => 'سمك'],
            ['name' => 'البياح', 'name_en' => 'BAYAH', 'category' => 'سمك'],
            ['name' => 'بوري', 'name_en' => 'BOORI', 'category' => 'سمك'],
            ['name' => 'بليمي', 'name_en' => 'BALIMI', 'category' => 'سمك'],
            ['name' => 'سيلفر', 'name_en' => 'SELVER', 'category' => 'سمك'],
            ['name' => 'قاروص', 'name_en' => 'SEA BASS', 'category' => 'سمك'],
            ['name' => 'لوت', 'name_en' => 'LOOT', 'category' => 'سمك'],
            ['name' => 'سهلية', 'name_en' => 'SHALIYA', 'category' => 'سمك'],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }

        // Default Accounts
        Account::create([
            'name' => 'الخزنة الرئيسية',
            'type' => 'cashbox',
            'balance' => 0,
        ]);

        Account::create([
            'name' => 'البنك',
            'type' => 'bank',
            'balance' => 0,
        ]);

        // Default Settings
        $settings = [
            // Invoice Settings
            ['key' => 'invoice_number_prefix', 'value' => 'INV-', 'type' => 'string', 'group' => 'invoices'],
            ['key' => 'invoice_number_digits', 'value' => '5', 'type' => 'integer', 'group' => 'invoices'],
            ['key' => 'invoice_edit_window_days', 'value' => '2', 'type' => 'integer', 'group' => 'invoices'],

            // Collection Settings
            ['key' => 'collection_number_prefix', 'value' => 'COL-', 'type' => 'string', 'group' => 'collections'],
            ['key' => 'collection_number_digits', 'value' => '5', 'type' => 'integer', 'group' => 'collections'],

            // Shipment Settings
            ['key' => 'shipment_number_prefix', 'value' => 'SHP-', 'type' => 'string', 'group' => 'shipments'],
            ['key' => 'shipment_number_digits', 'value' => '5', 'type' => 'integer', 'group' => 'shipments'],
            ['key' => 'shipment_auto_close_days', 'value' => '7', 'type' => 'integer', 'group' => 'shipments'],

            // Return Settings
            ['key' => 'return_number_prefix', 'value' => 'RET-', 'type' => 'string', 'group' => 'returns'],
            ['key' => 'return_number_digits', 'value' => '5', 'type' => 'integer', 'group' => 'returns'],

            // Expense Settings
            ['key' => 'expense_number_prefix', 'value' => 'EXP-', 'type' => 'string', 'group' => 'expenses'],
            ['key' => 'expense_number_digits', 'value' => '5', 'type' => 'integer', 'group' => 'expenses'],

            // AI Settings
            ['key' => 'price_anomaly_threshold', 'value' => '0.30', 'type' => 'string', 'group' => 'ai'],

            // General
            ['key' => 'company_name', 'value' => 'شركة المخزون', 'type' => 'string', 'group' => 'general'],
            ['key' => 'company_phone', 'value' => '', 'type' => 'string', 'group' => 'general'],
            ['key' => 'company_address', 'value' => '', 'type' => 'string', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }

        $this->command->info('✅ Initial data seeded successfully!');
        $this->command->info('   - 10 Products');
        $this->command->info('   - 2 Accounts (Cashbox + Bank)');
        $this->command->info('   - ' . count($settings) . ' Settings');
    }
}
