<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-Correction Flow - Add correction columns to existing tables
 * 
 * Allows negative collections (for refunds) - User approved: Y
 */
return new class extends Migration {
    public function up(): void
    {
        // Invoices: Add correction support
        Schema::table('invoices', function (Blueprint $table) {
            // Link to original invoice (for correction invoices)
            $table->foreignId('original_invoice_id')
                ->nullable()
                ->after('status')
                ->constrained('invoices')
                ->nullOnDelete();

            // Correction sequence (1st, 2nd, 3rd correction of same invoice)
            $table->unsignedInteger('correction_sequence')
                ->default(0)
                ->after('original_invoice_id');

            // Index for finding corrections
            $table->index('original_invoice_id');
        });

        // Collections: Add correction support + allow negative amounts
        Schema::table('collections', function (Blueprint $table) {
            // Link to original collection
            $table->foreignId('original_collection_id')
                ->nullable()
                ->after('status')
                ->constrained('collections')
                ->nullOnDelete();

            // Correction sequence
            $table->unsignedInteger('correction_sequence')
                ->default(0)
                ->after('original_collection_id');

            // Index
            $table->index('original_collection_id');
        });

        // Note: Collections.amount column already supports decimal
        // Negative values allowed for refunds (User approved: Y)
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['original_invoice_id']);
            $table->dropIndex(['original_invoice_id']);
            $table->dropColumn(['original_invoice_id', 'correction_sequence']);
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->dropForeign(['original_collection_id']);
            $table->dropIndex(['original_collection_id']);
            $table->dropColumn(['original_collection_id', 'correction_sequence']);
        });
    }
};
