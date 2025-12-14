<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Daily Reports Table (التقارير اليومية)
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            // Totals
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_collections', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('cash_balance', 15, 2)->default(0);
            $table->decimal('bank_balance', 15, 2)->default(0);

            // Counts
            $table->integer('invoices_count')->default(0);
            $table->integer('collections_count')->default(0);
            $table->integer('expenses_count')->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Daily Report Details Table (تفاصيل التقرير اليومي)
        Schema::create('daily_report_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['sale', 'collection', 'expense']);
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_type');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        // Settings Table (الإعدادات)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json
            $table->string('group')->default('general');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Audit Logs Table (سجل العمليات)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('action'); // created, updated, deleted, cancelled
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });

        // AI Alerts Table (تنبيهات الذكاء الاصطناعي)
        Schema::create('ai_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // price_anomaly, shipment_delay, fifo_error
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_read']);
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_alerts');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('daily_report_details');
        Schema::dropIfExists('daily_reports');
    }
};
