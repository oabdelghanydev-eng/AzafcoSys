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
        Schema::create('corrections', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship (Invoice, Collection)
            $table->string('correctable_type', 100);
            $table->unsignedBigInteger('correctable_id');
            $table->index(['correctable_type', 'correctable_id']);

            // Correction details
            $table->enum('correction_type', ['adjustment', 'reversal', 'reallocation'])
                ->comment('Type of correction being made');

            // Values
            $table->decimal('original_value', 15, 2);
            $table->decimal('adjustment_value', 15, 2)
                ->comment('Can be negative for credit notes');
            $table->decimal('new_value', 15, 2);

            // Reason
            $table->text('reason');
            $table->string('reason_code', 50)->nullable();

            // Sequence tracking
            $table->integer('correction_sequence')
                ->unsigned()
                ->default(1)
                ->comment('1st, 2nd, 3rd correction for same record');

            // Maker-Checker workflow
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            // Audit trail
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');

            // Indexes for performance
            $table->index('status');
            $table->index('created_by');
            $table->index('created_at');

            // Unique constraint to prevent duplicate corrections
            $table->unique(
                ['correctable_type', 'correctable_id', 'correction_sequence'],
                'unique_correction_per_record'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
