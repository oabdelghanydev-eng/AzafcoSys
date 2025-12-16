<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-Correction Flow - Corrections Reference Table
 *
 * Best Practice: Track all corrections with full audit trail
 * - Never delete original records
 * - Create correction entries that offset
 * - Maintain complete history
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corrections', function (Blueprint $table) {
            $table->id();

            // Polymorphic relation to corrected record
            $table->string('correctable_type', 100); // 'Invoice', 'Collection'
            $table->unsignedBigInteger('correctable_id');

            // Correction type
            $table->enum('correction_type', [
                'adjustment',    // Value adjustment
                'reversal',      // Full reversal
                'reallocation',  // Collection reallocation
            ]);

            // Amounts
            $table->decimal('original_value', 15, 2);
            $table->decimal('adjustment_value', 15, 2); // Can be negative
            $table->decimal('new_value', 15, 2);

            // Metadata
            $table->text('reason');
            $table->string('reason_code', 50)->nullable(); // Standardized codes
            $table->text('notes')->nullable();

            // Sequence for same record (1st, 2nd, 3rd correction)
            $table->unsignedInteger('correction_sequence')->default(1);

            // Approval workflow (Maker-Checker)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['correctable_type', 'correctable_id'], 'idx_correctable');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
