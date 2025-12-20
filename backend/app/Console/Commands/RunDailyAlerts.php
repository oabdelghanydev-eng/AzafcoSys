<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class RunDailyAlerts extends Command
{
    protected $signature = 'alerts:daily';
    protected $description = 'Run daily alert checks (price anomalies, shipment delays, overdue customers)';

    public function handle(AlertService $alertService): int
    {
        $this->info("🔔 Running daily alert checks...\n");

        try {
            $alerts = $alertService->runDailyChecks();

            $priceAlerts = collect($alerts)->where('type', 'price_anomaly')->count();
            $shipmentAlerts = collect($alerts)->where('type', 'shipment_delay')->count();
            $overdueAlerts = collect($alerts)->where('type', 'overdue_customer')->count();

            $this->table(['Type', 'Count'], [
                ['Price Anomalies', $priceAlerts],
                ['Shipment Delays', $shipmentAlerts],
                ['Overdue Customers', $overdueAlerts],
                ['Total', count($alerts)],
            ]);

            if (count($alerts) > 0) {
                $this->info("\n✅ " . count($alerts) . " alert(s) created and sent to Telegram!");
            } else {
                $this->info("\n✅ No alerts detected. All good!");
            }

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
