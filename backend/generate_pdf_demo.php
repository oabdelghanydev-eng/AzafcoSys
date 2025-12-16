<?php

/**
 * Generate Demo PDF Report
 * Run: php generate_pdf_demo.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Reports\DailyClosingReportService;
use App\Services\Reports\PdfGeneratorService;

echo "Generating Daily Report PDF...\n";

$reportService = app(DailyClosingReportService::class);
$pdfService = app(PdfGeneratorService::class);

$date = '2025-12-15';
$data = $reportService->generate($date);

// Debug: show what data we have
echo "Data found:\n";
echo "- Invoice Items: " . $data['invoiceItems']->count() . "\n";
echo "- Collections: " . $data['collections']->count() . "\n";
echo "- Expenses: " . $data['expenses']->count() . "\n";
echo "- Transfers: " . $data['transfers']->count() . "\n";
echo "- New Shipments: " . $data['newShipments']->count() . "\n";
echo "- Total Sales: " . number_format($data['totalSales'], 2) . "\n";

$filename = 'daily_report_arabic_' . $date . '.pdf';
$path = $pdfService->save('reports.daily-closing', $data, $filename);

echo "\n✅ PDF saved to: " . $path . "\n";
echo "Open this file to view the report.\n";
