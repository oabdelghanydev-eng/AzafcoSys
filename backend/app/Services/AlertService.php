<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class AlertService
{
    private TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Run all daily checks and send alerts
     */
    public function runDailyChecks(): array
    {
        $alerts = [];

        // 1. Price Anomaly Detection
        $priceAlerts = $this->detectPriceAnomalies();
        $alerts = array_merge($alerts, $priceAlerts);

        // 2. Shipment Delay Detection
        $shipmentAlerts = $this->detectShipmentDelays();
        $alerts = array_merge($alerts, $shipmentAlerts);

        // 3. Overdue Customer Detection
        $overdueAlerts = $this->detectOverdueCustomers();
        $alerts = array_merge($alerts, $overdueAlerts);

        // Send summary to Telegram
        if (count($alerts) > 0) {
            $this->sendAlertsSummaryToTelegram($alerts);
        }

        return $alerts;
    }

    /**
     * Detect price anomalies in yesterday's invoices
     */
    public function detectPriceAnomalies(): array
    {
        $alerts = [];
        $threshold = (float) Setting::getValue('price_anomaly_threshold', 30); // 30% default
        $yesterday = now()->subDay()->toDateString();

        // Get yesterday's invoice items grouped by product
        $items = InvoiceItem::whereHas('invoice', function ($q) use ($yesterday) {
            $q->where('date', $yesterday)->where('status', 'active');
        })
            ->with(['product', 'invoice.customer'])
            ->get();

        foreach ($items as $item) {
            // Get average price for this product (last 30 days, excluding yesterday)
            $avgPrice = InvoiceItem::whereHas('invoice', function ($q) use ($yesterday) {
                $q->where('date', '<', $yesterday)
                    ->where('date', '>=', now()->subDays(30)->toDateString())
                    ->where('status', 'active');
            })
                ->where('product_id', $item->product_id)
                ->avg('unit_price');

            if (!$avgPrice || $avgPrice == 0) {
                continue; // No historical data
            }

            // Calculate deviation
            $deviation = abs($item->unit_price - $avgPrice) / $avgPrice * 100;

            if ($deviation >= $threshold) {
                $alert = Alert::create([
                    'type' => 'price_anomaly',
                    'severity' => $deviation >= 50 ? 'high' : 'medium',
                    'title' => 'سعر شاذ - ' . $item->product->name,
                    'message' => sprintf(
                        'الصنف: %s | السعر: %.2f | المتوسط: %.2f | الانحراف: %.1f%%',
                        $item->product->bilingual_name,
                        $item->unit_price,
                        $avgPrice,
                        $deviation
                    ),
                    'data' => [
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->bilingual_name,
                        'invoice_id' => $item->invoice_id,
                        'invoice_number' => $item->invoice->invoice_number,
                        'customer_name' => $item->invoice->customer->name,
                        'current_price' => $item->unit_price,
                        'average_price' => $avgPrice,
                        'deviation_percent' => $deviation,
                    ],
                ]);

                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Detect shipments open for too long
     */
    public function detectShipmentDelays(): array
    {
        $alerts = [];
        $expectedDays = (int) Setting::getValue('expected_shipment_duration', 14);

        $delayedShipments = Shipment::where('status', 'open')
            ->where('date', '<', now()->subDays($expectedDays))
            ->with('supplier')
            ->get();

        foreach ($delayedShipments as $shipment) {
            $openDays = (int) abs(now()->diffInDays($shipment->date));

            // Check if alert already exists for this shipment
            $existingAlert = Alert::where('type', 'shipment_delay')
                ->where('data->shipment_id', $shipment->id)
                ->where('status', 'active')
                ->first();

            if ($existingAlert) {
                continue;
            }

            $alert = Alert::create([
                'type' => 'shipment_delay',
                'severity' => $openDays > ($expectedDays * 2) ? 'high' : 'medium',
                'title' => 'شحنة متأخرة - ' . $shipment->number,
                'message' => sprintf(
                    'الشحنة %s من %s مفتوحة منذ %d يوم (المتوقع %d يوم)',
                    $shipment->number,
                    $shipment->supplier->name,
                    $openDays,
                    $expectedDays
                ),
                'data' => [
                    'shipment_id' => $shipment->id,
                    'shipment_number' => $shipment->number,
                    'supplier_name' => $shipment->supplier->name,
                    'open_days' => $openDays,
                    'expected_days' => $expectedDays,
                ],
            ]);

            $alerts[] = $alert;
        }

        return $alerts;
    }

    /**
     * Detect customers with overdue invoices
     */
    public function detectOverdueCustomers(): array
    {
        $alerts = [];
        $overdueDays = (int) Setting::getValue('overdue_threshold_days', 30);

        $overdueCustomers = Customer::where('balance', '>', 0)
            ->whereHas('invoices', function ($q) use ($overdueDays) {
                $q->where('balance', '>', 0)
                    ->where('status', 'active')
                    ->where('date', '<', now()->subDays($overdueDays));
            })
            ->with([
                'invoices' => function ($q) use ($overdueDays) {
                    $q->where('balance', '>', 0)
                        ->where('date', '<', now()->subDays($overdueDays));
                }
            ])
            ->get();

        foreach ($overdueCustomers as $customer) {
            // Check if alert already exists for this customer
            $existingAlert = Alert::where('type', 'overdue_customer')
                ->where('data->customer_id', $customer->id)
                ->where('status', 'active')
                ->first();

            if ($existingAlert) {
                continue;
            }

            $oldestInvoice = $customer->invoices->sortBy('date')->first();
            $daysSinceOldest = now()->diffInDays($oldestInvoice->date);

            $alert = Alert::create([
                'type' => 'overdue_customer',
                'severity' => $daysSinceOldest > ($overdueDays * 2) ? 'high' : 'medium',
                'title' => 'عميل متأخر - ' . $customer->name,
                'message' => sprintf(
                    'العميل %s له رصيد %.2f مستحق منذ %d يوم',
                    $customer->name,
                    $customer->balance,
                    $daysSinceOldest
                ),
                'data' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'balance' => $customer->balance,
                    'days_overdue' => $daysSinceOldest,
                    'overdue_invoices_count' => $customer->invoices->count(),
                ],
            ]);

            $alerts[] = $alert;
        }

        return $alerts;
    }

    /**
     * Send alerts summary to Telegram
     */
    private function sendAlertsSummaryToTelegram(array $alerts): void
    {
        if (!$this->telegram->isConfigured()) {
            return;
        }

        $priceAlerts = collect($alerts)->where('type', 'price_anomaly');
        $shipmentAlerts = collect($alerts)->where('type', 'shipment_delay');
        $overdueAlerts = collect($alerts)->where('type', 'overdue_customer');

        $message = "🚨 <b>تنبيهات النظام اليومية</b>\n";
        $message .= "📅 " . now()->format('Y-m-d') . "\n\n";

        if ($priceAlerts->isNotEmpty()) {
            $message .= "💰 <b>أسعار شاذة ({$priceAlerts->count()}):</b>\n";
            foreach ($priceAlerts->take(5) as $alert) {
                $data = $alert->data;
                $avgFormatted = number_format($data['average_price'], 2);
                $message .= "• {$data['product_name']}: {$data['current_price']} (متوسط: {$avgFormatted})\n";
            }
            if ($priceAlerts->count() > 5) {
                $message .= "... و " . ($priceAlerts->count() - 5) . " تنبيهات أخرى\n";
            }
            $message .= "\n";
        }

        if ($shipmentAlerts->isNotEmpty()) {
            $message .= "📦 <b>شحنات متأخرة ({$shipmentAlerts->count()}):</b>\n";
            foreach ($shipmentAlerts as $alert) {
                $data = $alert->data;
                $message .= "• {$data['shipment_number']}: {$data['open_days']} يوم\n";
            }
            $message .= "\n";
        }

        if ($overdueAlerts->isNotEmpty()) {
            $message .= "👤 <b>عملاء متأخرين ({$overdueAlerts->count()}):</b>\n";
            foreach ($overdueAlerts->take(5) as $alert) {
                $data = $alert->data;
                $message .= "• {$data['customer_name']}: {$data['balance']} ({$data['days_overdue']} يوم)\n";
            }
            if ($overdueAlerts->count() > 5) {
                $message .= "... و " . ($overdueAlerts->count() - 5) . " عملاء آخرين\n";
            }
        }

        $this->telegram->sendMessage($message);
    }
}
