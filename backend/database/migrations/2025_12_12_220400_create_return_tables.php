<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Returns Table (المرتجعات)
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('date')->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'date']);
        });

        // Return Items Table (بنود المرتجع)
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->foreignId('target_shipment_item_id')->constrained('shipment_items')->cascadeOnDelete();

            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 15, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
