<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add immutable fifo_sequence for proper FIFO ordering
 *
 * Best Practice:
 * - fifo_sequence: للقرارات المحاسبية (FIFO)
 * - date: للتقارير فقط
 *
 * السبب: التسلسل غير قابل للتعديل ومضمون الترتيب
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Add fifo_sequence - auto-increment separate from id
            // This is immutable and represents the true FIFO order
            $table->unsignedBigInteger('fifo_sequence')->nullable()->after('id');
            $table->index('fifo_sequence');
        });

        // Populate existing shipments with sequence based on current order
        // Using id as initial sequence (already represents creation order)
        DB::statement('UPDATE shipments SET fifo_sequence = id WHERE fifo_sequence IS NULL');

        // Make column NOT NULL after populating
        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('fifo_sequence')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['fifo_sequence']);
            $table->dropColumn('fifo_sequence');
        });
    }
};
