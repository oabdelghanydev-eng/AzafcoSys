<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Collections Table (التحصيلات)
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['cash', 'bank'])->default('cash');
            $table->enum('distribution_method', ['auto', 'manual'])->default('auto');
            $table->decimal('allocated_amount', 15, 2)->default(0); // الموزع على فواتير
            $table->decimal('unallocated_amount', 15, 2)->default(0); // المتبقي (رصيد دائن)
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'date']);
        });

        // Collection Allocations Table (توزيع التحصيل على الفواتير)
        Schema::create('collection_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['collection_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_allocations');
        Schema::dropIfExists('collections');
    }
};
