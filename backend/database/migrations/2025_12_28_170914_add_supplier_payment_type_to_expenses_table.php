<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Add 'supplier_payment' to the expenses type enum.
     * This allows tracking direct payments to suppliers.
     */
    public function up(): void
    {
        // MySQL requires modifying enum via raw SQL
        DB::statement("ALTER TABLE expenses MODIFY COLUMN type ENUM('company', 'supplier', 'supplier_payment') DEFAULT 'company'");
    }

    /**
     * Reverse the migration - remove supplier_payment from enum.
     */
    public function down(): void
    {
        // First update any supplier_payment records to supplier
        DB::table('expenses')
            ->where('type', 'supplier_payment')
            ->update(['type' => 'supplier']);

        // Then modify enum back
        DB::statement("ALTER TABLE expenses MODIFY COLUMN type ENUM('company', 'supplier') DEFAULT 'company'");
    }
};
