<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add cartons_returned to track carton returns against invoice items.
     * 
     * This is critical for:
     * - Inventory restoration (sold_cartons tracking)
     * - Preventing over-return of cartons
     * - Accurate carton-based inventory management
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->integer('cartons_returned')->default(0)->after('quantity_returned');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('cartons_returned');
        });
    }
};
