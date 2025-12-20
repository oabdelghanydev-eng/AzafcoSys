<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add cartons column to return_items for consistency with invoice_items
     * 
     * Before: quantity was being used as cartons count (confusing)
     * After: cartons = count, quantity = actual weight (consistent)
     */
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            // Add cartons column after target_shipment_item_id
            $table->integer('cartons')->default(0)->after('target_shipment_item_id');
        });

        // Migrate existing data: copy quantity (which was cartons) to cartons column
        // Then recalculate quantity as weight (cartons * weight_per_unit)
        DB::statement('
            UPDATE return_items ri
            JOIN shipment_items si ON ri.target_shipment_item_id = si.id
            SET 
                ri.cartons = CAST(ri.quantity AS SIGNED),
                ri.quantity = CAST(ri.quantity AS SIGNED) * si.weight_per_unit
        ');
    }

    public function down(): void
    {
        // Revert: copy cartons back to quantity
        DB::statement('
            UPDATE return_items ri
            SET ri.quantity = ri.cartons
        ');

        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn('cartons');
        });
    }
};
