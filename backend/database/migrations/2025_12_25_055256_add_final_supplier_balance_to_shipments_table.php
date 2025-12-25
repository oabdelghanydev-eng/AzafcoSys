<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Store the final supplier balance when settling for accurate reporting
            $table->decimal('final_supplier_balance', 15, 2)->nullable()->after('total_supplier_expenses');
            $table->decimal('previous_supplier_balance', 15, 2)->nullable()->after('final_supplier_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['final_supplier_balance', 'previous_supplier_balance']);
        });
    }
};
