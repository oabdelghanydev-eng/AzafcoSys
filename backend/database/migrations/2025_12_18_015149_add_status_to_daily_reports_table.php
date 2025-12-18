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
        Schema::table('daily_reports', function (Blueprint $table) {
            // Status
            $table->enum('status', ['open', 'closed'])->default('open')->after('date');

            // Opening/Closing Balances
            $table->decimal('cashbox_opening', 15, 2)->default(0)->after('status');
            $table->decimal('bank_opening', 15, 2)->default(0)->after('cashbox_opening');
            $table->decimal('cashbox_closing', 15, 2)->default(0)->after('bank_balance');
            $table->decimal('bank_closing', 15, 2)->default(0)->after('cashbox_closing');
            $table->decimal('cashbox_difference', 15, 2)->default(0)->after('bank_closing');
            $table->decimal('net_day', 15, 2)->default(0)->after('cashbox_difference');

            // Split totals
            $table->decimal('total_collections_cash', 15, 2)->default(0)->after('total_sales');
            $table->decimal('total_collections_bank', 15, 2)->default(0)->after('total_collections_cash');
            $table->decimal('total_expenses_cash', 15, 2)->default(0)->after('total_expenses');
            $table->decimal('total_expenses_bank', 15, 2)->default(0)->after('total_expenses_cash');
            $table->decimal('total_wastage', 15, 2)->default(0)->after('total_expenses_bank');
            $table->decimal('total_transfers_in', 15, 2)->default(0)->after('total_wastage');
            $table->decimal('total_transfers_out', 15, 2)->default(0)->after('total_transfers_in');

            // User tracking
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete()->after('notes');
            $table->timestamp('closed_at')->nullable()->after('opened_by');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete()->after('closed_at');
            $table->timestamp('reopened_at')->nullable()->after('closed_by');
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete()->after('reopened_at');

            // AI Alerts
            $table->json('ai_alerts')->nullable()->after('reopened_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $table) {
            $table->dropForeign(['opened_by']);
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['reopened_by']);
            $table->dropColumn([
                'status',
                'cashbox_opening',
                'bank_opening',
                'cashbox_closing',
                'bank_closing',
                'cashbox_difference',
                'net_day',
                'total_collections_cash',
                'total_collections_bank',
                'total_expenses_cash',
                'total_expenses_bank',
                'total_wastage',
                'total_transfers_in',
                'total_transfers_out',
                'opened_by',
                'closed_at',
                'closed_by',
                'reopened_at',
                'reopened_by',
                'ai_alerts'
            ]);
        });
    }
};
