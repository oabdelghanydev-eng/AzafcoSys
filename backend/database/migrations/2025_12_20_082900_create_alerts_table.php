<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // price_anomaly, shipment_delay, overdue_customer
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->enum('status', ['active', 'acknowledged', 'dismissed'])->default('active');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
