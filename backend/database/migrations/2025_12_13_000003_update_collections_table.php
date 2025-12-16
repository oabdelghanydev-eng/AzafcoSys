<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تحسين 2025-12-13: تحديث جدول التحصيلات ليدعم FIFO/LIFO والحالة
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Add status column
            $table->enum('status', ['confirmed', 'cancelled'])->default('confirmed')->after('unallocated_amount');
        });

        // Update distribution_method enum to support new values
        // Note: SQLite doesn't support modifying enums, using raw SQL for MySQL
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE collections MODIFY COLUMN distribution_method ENUM('auto', 'manual', 'oldest_first', 'newest_first') DEFAULT 'auto'");
        }
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE collections MODIFY COLUMN distribution_method ENUM('auto', 'manual') DEFAULT 'auto'");
        }
    }
};
