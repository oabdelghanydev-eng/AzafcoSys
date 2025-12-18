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
            $table->decimal('total_sales', 15, 2)->nullable()->after('settled_at');
            $table->decimal('total_wastage', 15, 2)->nullable()->after('total_sales');
            $table->decimal('total_carryover_out', 15, 2)->nullable()->after('total_wastage');
            $table->decimal('total_supplier_expenses', 15, 2)->nullable()->after('total_carryover_out');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn([
                'total_sales',
                'total_wastage',
                'total_carryover_out',
                'total_supplier_expenses',
            ]);
        });
    }
};
