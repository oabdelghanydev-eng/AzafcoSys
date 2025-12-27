<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ARCHITECTURAL DECISION (2025-12-27):
 * Add CHECK constraint to prevent inventory overselling.
 * 
 * This ensures sold_cartons can never exceed available cartons,
 * providing database-level protection against inventory corruption.
 * 
 * Note: MySQL 8.0.16+ enforces CHECK constraints. Earlier versions ignore them.
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add CHECK constraint to prevent overselling
        // sold_cartons should never exceed available stock (cartons + carryover_in - carryover_out)
        DB::statement('
            ALTER TABLE shipment_items 
            ADD CONSTRAINT chk_sold_cartons_not_exceed_available 
            CHECK (sold_cartons <= (cartons + carryover_in_cartons - carryover_out_cartons))
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('
            ALTER TABLE shipment_items 
            DROP CONSTRAINT chk_sold_cartons_not_exceed_available
        ');
    }
};
