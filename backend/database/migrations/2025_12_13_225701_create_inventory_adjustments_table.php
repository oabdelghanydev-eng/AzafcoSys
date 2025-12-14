<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-Correction Flow - Inventory Adjustments Table
 * 
 * Best Practice: Track inventory corrections with approval
 * - Physical count differences
 * - Damage/wastage
 * - Error corrections
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();

            // Reference
            $table->foreignId('shipment_item_id')->constrained('shipment_items');
            $table->foreignId('product_id')->constrained('products');

            // Quantities
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);
            $table->decimal('quantity_change', 15, 3); // Can be negative

            // Reason
            $table->enum('adjustment_type', [
                'physical_count',  // جرد فعلي
                'damage',          // تالف
                'theft',           // سرقة
                'error',           // خطأ إدخال
                'transfer',        // نقل بين شحنات
                'expiry',          // انتهاء صلاحية
            ]);
            $table->text('reason');

            // Cost Impact
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost_impact', 15, 2);

            // Approval workflow (Maker-Checker) - Required for all
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('adjustment_type');
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
