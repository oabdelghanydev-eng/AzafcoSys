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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();

            // Unique adjustment number
            $table->string('adjustment_number', 50)->unique();

            // References
            $table->unsignedBigInteger('shipment_item_id');
            $table->unsignedBigInteger('product_id');

            // Quantity changes
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);
            $table->decimal('quantity_change', 15, 3)
                ->comment('Positive = increase, Negative = decrease');

            // Adjustment type
            $table->enum('adjustment_type', [
                'physical_count',
                'damage',
                'theft',
                'error',
                'transfer',
                'expiry'
            ])->comment('Reason for adjustment');

            $table->text('reason');

            // Cost impact
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost_impact', 15, 2)
                ->comment('quantity_change * unit_cost');

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
            $table->foreign('shipment_item_id')->references('id')->on('shipment_items');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');

            // Indexes for performance
            $table->index('shipment_item_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
