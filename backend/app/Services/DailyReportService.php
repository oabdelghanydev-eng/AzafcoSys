<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\DailyReport;
use App\Models\Setting;
use App\Services\AlertService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;

/**
 * DailyReportService
 *
 * Handles daily report operations with database-based session tracking.
 * All users work on the same daily report.
 */
class DailyReportService
{
    /**
     * Get available dates for opening
     * التواريخ المتاحة لفتح يومية
     */
    public function getAvailableDates(): array
    {
        $backdatedDays = (int) Setting::getValue('backdated_days', 2);
        $startDate = today()->subDays($backdatedDays);
        $endDate = today();

        $dates = [];

        for ($date = clone $startDate; $date <= $endDate; $date->addDay()) {
            $dateStr = $date->toDateString();
            $report = DailyReport::where('date', $dateStr)->first();

            // Available if: no report exists OR report is open
            if (!$report || $report->status === 'open') {
                $dates[] = [
                    'date' => $dateStr,
                    'day_name' => $date->translatedFormat('l'),
                    'status' => $report ? 'open' : 'new',
                    'has_operations' => $report ? $this->hasOperations($report) : false,
                ];
            }
        }

        return $dates;
    }

    /**
     * Get current open daily report (shared by all users)
     * اليومية المفتوحة حالياً
     */
    public function getCurrentOpenReport(): ?DailyReport
    {
        return DailyReport::where('status', 'open')
            ->orderByDesc('date')
            ->first();
    }

    /**
     * Open/Create a daily report
     * فتح يومية (مشتركة لكل المستخدمين)
     */
    public function openDay(string $date): DailyReport
    {
        // Validate date is available
        $this->validateDateAvailable($date);

        return DB::transaction(function () use ($date) {
            // Check if report exists
            $report = DailyReport::where('date', $date)->first();

            if ($report) {
                if ($report->status === 'closed') {
                    throw new BusinessException(
                        'DAY_001',
                        'هذا اليوم مغلق. استخدم إعادة الفتح.',
                        'This day is closed. Use reopen.'
                    );
                }

                // Already open - just return it
                return $report;
            }

            // Create new daily report
            $report = DailyReport::create([
                'date' => $date,
                'cashbox_opening' => $this->getLastClosingBalance('cashbox'),
                'bank_opening' => $this->getLastClosingBalance('bank'),
                'status' => 'open',
                'opened_by' => auth()->id(),
            ]);

            // Log user event
            $this->logUserEvent('daily_open', "فتح يومية {$date}");

            return $report;
        });
    }

    /**
     * Close daily report
     * إغلاق اليومية مع حساب الإجماليات
     */
    public function closeDay(DailyReport $report): DailyReport
    {
        if ($report->status === 'closed') {
            throw new BusinessException(
                'DAY_002',
                'اليومية مغلقة بالفعل',
                'Daily report is already closed'
            );
        }

        return DB::transaction(function () use ($report) {
            // Calculate totals from invoices and collections
            $date = $report->date;

            // Invoices totals (excluding cancelled and wastage)
            $invoiceStats = \App\Models\Invoice::where('date', $date)
                ->where('status', 'active')
                ->where('type', '!=', 'wastage')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
                ->first();

            // Collections totals (excluding cancelled)
            $collectionStats = \App\Models\Collection::where('date', $date)
                ->where('status', '!=', 'cancelled')
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
                ->first();

            // Expenses totals
            $expenseStats = \App\Models\Expense::where('date', $date)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
                ->first();

            // Calculate closing balances
            $cashboxClosing = (float) $report->cashbox_opening
                + (float) ($collectionStats->total ?? 0)
                - (float) ($expenseStats->total ?? 0);

            $report->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                // Totals
                'total_sales' => $invoiceStats->total ?? 0,
                'total_collections' => $collectionStats->total ?? 0,
                'total_expenses' => $expenseStats->total ?? 0,
                'invoices_count' => $invoiceStats->count ?? 0,
                'collections_count' => $collectionStats->count ?? 0,
                'expenses_count' => $expenseStats->count ?? 0,
                // Closing balances
                'cashbox_closing' => $cashboxClosing,
            ]);

            // Log user event
            $this->logUserEvent('daily_close', "إغلاق يومية {$report->date}");

            // Send Telegram notification (async - don't block)
            $this->sendDailyReportToTelegram($report->fresh());

            // Run daily alerts check
            $this->runDailyAlerts();

            return $report->fresh();
        });
    }

    /**
     * Run daily alerts after closing
     */
    private function runDailyAlerts(): void
    {
        try {
            $alertService = app(AlertService::class);
            $alertService->runDailyChecks();
        } catch (\Exception $e) {
            \Log::warning('Failed to run daily alerts', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send daily report PDF to Telegram
     */
    private function sendDailyReportToTelegram(DailyReport $report): void
    {
        try {
            $telegram = app(TelegramService::class);

            if (!$telegram->isConfigured()) {
                return;
            }

            // Generate PDF
            $pdfService = app(\App\Services\Reports\PdfGeneratorService::class);
            $reportService = app(\App\Services\Reports\DailyClosingReportService::class);

            $dateString = $report->date instanceof \Carbon\Carbon
                ? $report->date->format('Y-m-d')
                : $report->date;

            $data = $reportService->generate($dateString);
            $filename = "reports/daily-report-{$dateString}.pdf";
            $path = $pdfService->save('reports.daily-closing', $data, $filename);

            // Send to Telegram
            $summary = [
                'total_sales' => $report->total_sales ?? 0,
                'total_collections' => $report->total_collections ?? 0,
                'total_expenses' => $report->total_expenses ?? 0,
            ];

            $telegram->sendDailyReport($path, $dateString, $summary);
        } catch (\Exception $e) {
            \Log::warning('Failed to send daily report to Telegram', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reopen a closed daily report
     * إعادة فتح يومية مغلقة
     */
    public function reopenDay(DailyReport $report): DailyReport
    {
        if ($report->status !== 'closed') {
            throw new BusinessException(
                'DAY_003',
                'اليومية مفتوحة بالفعل',
                'Daily report is already open'
            );
        }

        return DB::transaction(function () use ($report) {
            $report->update([
                'status' => 'open',
                'reopened_at' => now(),
                'reopened_by' => auth()->id(),
            ]);

            // Log user event
            $this->logUserEvent('daily_reopen', "إعادة فتح يومية {$report->date}");

            return $report->fresh();
        });
    }

    /**
     * Get working date from current open report
     * تاريخ العمل الحالي
     */
    public function getWorkingDate(): ?string
    {
        $report = $this->getCurrentOpenReport();

        return $report?->date;
    }

    /**
     * Ensure there's an open daily report for operations
     * التأكد من وجود يومية مفتوحة
     */
    public function ensureOpenReport(): DailyReport
    {
        $report = $this->getCurrentOpenReport();

        if (!$report) {
            throw new BusinessException(
                'DAY_004',
                'يجب فتح يومية أولاً',
                'Must open a daily report first'
            );
        }

        return $report;
    }

    /**
     * Validate date is available for opening
     */
    private function validateDateAvailable(string $date): void
    {
        $backdatedDays = (int) Setting::getValue('backdated_days', 2);
        $startDate = today()->subDays($backdatedDays);
        $endDate = today();

        $dateObj = \Carbon\Carbon::parse($date);

        if ($dateObj < $startDate || $dateObj > $endDate) {
            throw new BusinessException(
                'DAY_005',
                'التاريخ خارج النطاق المسموح',
                'Date is outside allowed range'
            );
        }
    }

    /**
     * Get last closing balance
     */
    private function getLastClosingBalance(string $type): float
    {
        $lastReport = DailyReport::where('status', 'closed')
            ->orderByDesc('date')
            ->first();

        if ($lastReport) {
            // @phpstan-ignore nullCoalesce.expr (dynamic property access may be null)
            return (float) ($lastReport->{"{$type}_closing"} ?? 0);
        }

        // First day - use current account balance
        $account = Account::{$type}()->first();

        return (float) ($account?->balance ?? 0);
    }

    /**
     * Check if report has operations
     */
    private function hasOperations(DailyReport $report): bool
    {
        // Check if any operations exist for this date
        return \App\Models\Invoice::where('date', $report->date)->exists()
            || \App\Models\Collection::where('date', $report->date)->exists()
            || \App\Models\Expense::where('date', $report->date)->exists();
    }

    /**
     * Log user event to audit log
     */
    private function logUserEvent(string $action, string $description): void
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => DailyReport::class,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
