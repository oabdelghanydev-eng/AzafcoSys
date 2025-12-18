<?php

namespace App\Console\Commands;

use App\Services\Reports\DailyClosingReportService;
use App\Services\Reports\PdfGeneratorService;
use Illuminate\Console\Command;

class GenerateDailyReportPdf extends Command
{
    protected $signature = 'report:daily {date?}';
    protected $description = 'Generate Daily Closing Report PDF';

    public function handle(DailyClosingReportService $reportService, PdfGeneratorService $pdfService)
    {
        $date = $this->argument('date') ?? now()->subDay()->toDateString();

        $this->info("📊 Generating Daily Report for: {$date}");

        try {
            $data = $reportService->generate($date);

            // Generate and save PDF using save() method
            $filename = "reports/daily-report-{$date}.pdf";
            $path = $pdfService->save('reports.daily-closing', $data, $filename);

            $this->info("✅ PDF saved to: {$path}");

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
