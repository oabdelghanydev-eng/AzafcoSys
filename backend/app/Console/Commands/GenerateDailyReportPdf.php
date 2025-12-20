<?php

namespace App\Console\Commands;

use App\Services\Reports\DailyClosingReportService;
use App\Services\Reports\PdfGeneratorService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class GenerateDailyReportPdf extends Command
{
    protected $signature = 'report:daily {date?} {--telegram : Send report to Telegram}';
    protected $description = 'Generate Daily Closing Report PDF';

    public function handle(
        DailyClosingReportService $reportService,
        PdfGeneratorService $pdfService,
        TelegramService $telegram
    ) {
        $date = $this->argument('date') ?? now()->subDay()->toDateString();
        $sendTelegram = $this->option('telegram');

        $this->info("📊 Generating Daily Report for: {$date}");

        try {
            $data = $reportService->generate($date);

            // Generate and save PDF using save() method
            $filename = "reports/daily-report-{$date}.pdf";
            $path = $pdfService->save('reports.daily-closing', $data, $filename);

            $this->info("✅ PDF saved to: {$path}");

            // Send to Telegram if requested
            if ($sendTelegram) {
                $this->info("📲 Sending to Telegram...");

                $summary = [
                    'total_sales' => $data['totalSales'] ?? 0,
                    'total_collections' => $data['totalCollections'] ?? 0,
                    'total_expenses' => $data['totalExpenses'] ?? 0,
                ];

                if ($telegram->sendDailyReport($path, $date, $summary)) {
                    $this->info("✅ Sent to Telegram!");
                } else {
                    $this->warn("⚠️ Failed to send to Telegram (check config)");
                }
            }

            // Show summary
            $this->table(['Metric', 'Value'], [
                ['Date', $date],
                ['Invoice Items', count($data['invoiceItems'] ?? [])],
                ['Collections', count($data['collections'] ?? [])],
                ['Expenses', count($data['expenses'] ?? [])],
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }
    }
}

