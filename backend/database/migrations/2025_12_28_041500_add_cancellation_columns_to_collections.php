<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add cancellation tracking columns to collections table
 * Per BL_Collections.md: Collections must support cancellation (not deletion)
 * 
 * Note: status column already exists from a previous migration
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Only add if not exists
            if (!Schema::hasColumn('collections', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('created_by')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('collections', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (Schema::hasColumn('collections', 'cancelled_by')) {
                $table->dropForeign(['cancelled_by']);
                $table->dropColumn('cancelled_by');
            }
            if (Schema::hasColumn('collections', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
