<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add opening_balance to accounts table.
 * 
 * This tracks the initial balance when the account was created,
 * separate from the current balance which changes with transactions.
 * 
 * For accounting audit:
 * - opening_balance: What we started with (immutable after first set)
 * - balance: Current balance (changes with transactions)
 * 
 * Formula: balance = opening_balance + SUM(inflows) - SUM(outflows)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->after('balance');
            $table->timestamp('opening_balance_set_at')->nullable()->after('opening_balance');
            $table->foreignId('opening_balance_set_by')->nullable()->after('opening_balance_set_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['opening_balance_set_by']);
            $table->dropColumn(['opening_balance', 'opening_balance_set_at', 'opening_balance_set_by']);
        });
    }
};
