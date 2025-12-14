<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Add code columns to customers and suppliers tables
     * As per Database_Schema.md requirements
     */
    public function up(): void
    {
        // Add code column to customers
        Schema::table('customers', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id');
        });

        // Generate codes for existing customers
        DB::table('customers')->orderBy('id')->each(function ($customer) {
            DB::table('customers')
                ->where('id', $customer->id)
                ->update(['code' => 'CUS-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT)]);
        });

        // Make code unique and not nullable
        Schema::table('customers', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->unique()->change();
        });

        // Add code column to suppliers
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id');
        });

        // Generate codes for existing suppliers
        DB::table('suppliers')->orderBy('id')->each(function ($supplier) {
            DB::table('suppliers')
                ->where('id', $supplier->id)
                ->update(['code' => 'SUP-' . str_pad($supplier->id, 5, '0', STR_PAD_LEFT)]);
        });

        // Make code unique and not nullable
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
