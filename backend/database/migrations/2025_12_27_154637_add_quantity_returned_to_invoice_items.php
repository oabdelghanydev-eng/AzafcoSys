<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add quantity_returned column to track returns against invoice items.
     * 
     * This enables:
     * - Validating return quantities don't exceed original sale
     * - Knowing remaining returnable quantity
     * - Accurate accounting reconciliation
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Track returned quantity (weight in kg)
            $table->decimal('quantity_returned', 10, 3)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('quantity_returned');
        });
    }
};
