<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    /**
     * Backfill quantity_returned from existing active returns.
     * 
     * This ensures data consistency for invoice items that
     * already have returns recorded before quantity_returned tracking.
     */
    public function up(): void
    {
        // Calculate and update quantity_returned for each invoice item
        // that has associated return items from active returns
        $updated = DB::update("
            UPDATE invoice_items ii
            SET quantity_returned = COALESCE((
                SELECT SUM(ri.quantity)
                FROM return_items ri
                JOIN returns r ON ri.return_id = r.id
                WHERE ri.original_invoice_item_id = ii.id
                AND r.status = 'active'
            ), 0)
            WHERE EXISTS (
                SELECT 1
                FROM return_items ri
                JOIN returns r ON ri.return_id = r.id
                WHERE ri.original_invoice_item_id = ii.id
                AND r.status = 'active'
            )
        ");

        Log::info("[Migration] Backfilled quantity_returned for {$updated} invoice items");
    }

    /**
     * Reverse the backfill (reset to 0)
     */
    public function down(): void
    {
        DB::update("UPDATE invoice_items SET quantity_returned = 0");
    }
};
