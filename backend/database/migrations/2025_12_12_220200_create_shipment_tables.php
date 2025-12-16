<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shipments Table (الشحنات)
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['open', 'closed', 'settled'])->default('open')->index();
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Shipment Items Table (أصناف الشحنة)
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Weight info
            $table->decimal('weight_per_unit', 8, 3); // الوزن لكل وحدة
            $table->string('weight_label')->nullable(); // مثلاً "2-3 كيلو"

            // Quantities
            $table->integer('cartons')->default(0);
            $table->decimal('initial_quantity', 10, 3)->default(0); // الكمية الأصلية
            $table->decimal('sold_quantity', 10, 3)->default(0);
            $table->decimal('remaining_quantity', 10, 3)->default(0)->index(); // FIFO critical
            $table->decimal('wastage_quantity', 10, 3)->default(0);
            $table->decimal('carryover_in_quantity', 10, 3)->default(0);  // وارد من شحنة سابقة
            $table->decimal('carryover_out_quantity', 10, 3)->default(0); // مُرحل لشحنة تالية

            // Cost
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);

            $table->timestamps();

            $table->index(['product_id', 'remaining_quantity', 'created_at']); // FIFO index
        });

        // Carryovers Table (الترحيلات)
        Schema::create('carryovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('from_shipment_item_id')->constrained('shipment_items')->cascadeOnDelete();
            $table->foreignId('to_shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('to_shipment_item_id')->constrained('shipment_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->enum('reason', ['end_of_shipment', 'late_return', 'adjustment']);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carryovers');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
