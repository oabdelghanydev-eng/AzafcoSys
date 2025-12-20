<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

        // Shipment Items Table (أصناف الشحنة) - Cartons-Based FIFO
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Weight info
            $table->decimal('weight_per_unit', 8, 3); // الوزن لكل كرتونة
            $table->string('weight_label')->nullable(); // مثلاً "2-3 كيلو"

            // Cartons tracking (الكراتين هي الوحدة الأساسية)
            $table->integer('cartons')->default(0);           // كراتين واردة
            $table->integer('sold_cartons')->default(0);      // كراتين مباعة
            $table->integer('carryover_in_cartons')->default(0);  // مرحل إليها
            $table->integer('carryover_out_cartons')->default(0); // مرحل منها

            // Wastage (يُحسب عند التصفية)
            $table->decimal('wastage_quantity', 10, 3)->default(0);

            // Cost
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);

            $table->timestamps();

            // FIFO index
            $table->index(['product_id', 'created_at'], 'fifo_cartons_index');
        });

        // Carryovers Table (الترحيلات)
        Schema::create('carryovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('from_shipment_item_id')->constrained('shipment_items')->cascadeOnDelete();
            $table->foreignId('to_shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('to_shipment_item_id')->constrained('shipment_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('cartons'); // عدد الكراتين المرحلة
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

