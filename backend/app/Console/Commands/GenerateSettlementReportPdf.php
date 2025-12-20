<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\Reports\PdfGeneratorService;
use App\Services\Reports\ShipmentSettlementReportService;
use Illuminate\Console\Command;

class GenerateSettlementReportPdf extends Command
{
    protected $signature = 'report:settlement {shipment_id?} {--status=closed : Shipment status to find}';
    protected $description = 'Generate Shipment Settlement Report PDF';

    public function handle(ShipmentSettlementReportService $reportService, PdfGeneratorService $pdfService)
    {
        $shipmentId = $this->argument('shipment_id');
        $status = $this->option('status');

        // Find shipment
        if ($shipmentId) {
            $shipment = Shipment::with(['supplier', 'items.product'])->find($shipmentId);
        } else {
            $shipment = Shipment::where('status', $status)
                ->with(['supplier', 'items.product'])
                ->latest()
                ->first();
        }

        if (!$shipment) {
            $this->error("❌ No shipment found!");
            return 1;
        }

        $this->info("📊 Generating Settlement Report for: {$shipment->number}");
        $this->info("   Supplier: {$shipment->supplier->name}");
        $this->info("   Status: {$shipment->status}");

        try {
            // Generate report data
            $data = $reportService->generate($shipment);

            // Save PDF
            $filename = "reports/settlement-{$shipment->number}.pdf";
            $path = $pdfService->save('reports.shipment-settlement', $data, $filename);

            $this->info("✅ PDF saved to: {$path}");

            // Show summary
            $this->newLine();
            $this->table(['Metric', 'Value'], [
                ['Shipment', $shipment->number],
                ['Supplier', $shipment->supplier->name],
                ['Total Sales', number_format($data['totalSales'], 2) . ' AED'],
                ['Commission (6%)', number_format($data['companyCommission'], 2) . ' AED'],
                ['Final Balance', number_format($data['finalSupplierBalance'], 2) . ' AED'],
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
