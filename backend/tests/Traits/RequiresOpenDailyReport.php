<?php

namespace Tests\Traits;

use App\Models\DailyReport;

/**
 * Trait for tests that require an open daily report.
 * Use this trait in Feature tests that create transactions.
 */
trait RequiresOpenDailyReport
{
    /**
     * Create an open daily report for today.
     * Call this in setUp() before creating transactions.
     */
    protected function createOpenDailyReport(): DailyReport
    {
        return DailyReport::factory()->create([
            'date' => now()->toDateString(),
            'status' => 'open',
        ]);
    }

    /**
     * Create an open daily report for a specific date.
     */
    protected function createOpenDailyReportForDate(string $date): DailyReport
    {
        return DailyReport::factory()->create([
            'date' => $date,
            'status' => 'open',
        ]);
    }
}
