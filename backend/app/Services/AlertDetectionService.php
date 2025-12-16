<?php

namespace App\Services;

use App\Models\AiAlert;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * Alert Detection Service
 * Epic 8: AI Alerts & Smart Rules
 * تصحيح 2025-12-16: تنفيذ قواعد الكشف التلقائي
 */
class AlertDetectionService
{
    /**
     * Run all alert detection rules
     */
    public function runAllDetections(): array
    {
        $alerts = [];

        $alerts['price_anomalies'] = $this->detectPriceAnomalies();
        $alerts['shipment_delays'] = $this->detectShipmentDelays();
        $alerts['overdue_customers'] = $this->detectOverdueCustomers();

        return $alerts;
    }

    /**
     * Detect price anomalies (EC-8.1)
     * Alert when price differs more than 30% from 30-day average
     */
    public function detectPriceAnomalies(float $threshold = 0.3): array
    {
        $alerts = [];

        // Get recent invoice items with significant price deviation
        $recentItems = InvoiceItem::query()
            ->select('product_id', 'unit_price')
            ->whereDate('created_at', now()->toDateString())
            ->with('product:id,name')
            ->get();

        foreach ($recentItems as $item) {
            // Calculate 30-day average for this product
            $avgPrice = InvoiceItem::where('product_id', $item->product_id)
                ->whereDate('created_at', '>=', now()->subDays(30))
                ->whereDate('created_at', '<', now()->toDateString())
                ->avg('unit_price');

            if ($avgPrice && $avgPrice > 0) {
                $deviation = abs($item->unit_price - $avgPrice) / $avgPrice;

                if ($deviation > $threshold) {
                    $alert = AiAlert::create([
                        'type' => 'price_anomaly',
                        'severity' => $deviation > 0.5 ? 'critical' : 'warning',
                        'title' => 'سعر شاذ',
                        'message' => "السعر {$item->unit_price} يختلف عن المتوسط {$avgPrice} بنسبة ".round($deviation * 100).'%',
                        'data' => [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product?->name,
                            'current_price' => $item->unit_price,
                            'avg_price' => round($avgPrice, 2),
                            'deviation_percent' => round($deviation * 100, 1),
                        ],
                        'model_type' => InvoiceItem::class,
                        'model_id' => $item->id,
                    ]);

                    $alerts[] = $alert;
                }
            }
        }

        return $alerts;
    }

    /**
     * Detect shipment delays (EC-8.2)
     * Alert when shipment is open longer than expected
     */
    public function detectShipmentDelays(): array
    {
        $alerts = [];

        $expectedDays = (int) Setting::getValue('expected_shipment_duration', 14);

        $delayedShipments = Shipment::where('status', 'open')
            ->whereDate('date', '<', now()->subDays($expectedDays))
            ->get();

        foreach ($delayedShipments as $shipment) {
            $openDays = $shipment->date->diffInDays(now());

            // Check if alert already exists for this shipment
            $existingAlert = AiAlert::where('model_type', Shipment::class)
                ->where('model_id', $shipment->id)
                ->where('type', 'shipment_delay')
                ->where('is_resolved', false)
                ->first();

            if (! $existingAlert) {
                $alert = AiAlert::create([
                    'type' => 'shipment_delay',
                    'severity' => $openDays > ($expectedDays * 2) ? 'critical' : 'warning',
                    'title' => 'شحنة متأخرة',
                    'message' => "الشحنة {$shipment->shipment_number} مفتوحة منذ {$openDays} يوم",
                    'data' => [
                        'shipment_id' => $shipment->id,
                        'shipment_number' => $shipment->shipment_number,
                        'open_date' => $shipment->date->toDateString(),
                        'open_days' => $openDays,
                        'expected_days' => $expectedDays,
                    ],
                    'model_type' => Shipment::class,
                    'model_id' => $shipment->id,
                ]);

                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Detect overdue customers (EC-8.3)
     * Alert when customer has unpaid invoices older than threshold
     */
    public function detectOverdueCustomers(): array
    {
        $alerts = [];

        $overdueDays = (int) Setting::getValue('overdue_threshold_days', 30);

        $overdueCustomers = Customer::where('balance', '>', 0)
            ->whereHas('invoices', function ($q) use ($overdueDays) {
                $q->where('balance', '>', 0)
                    ->where('status', 'active')
                    ->whereDate('date', '<', now()->subDays($overdueDays));
            })
            ->get();

        foreach ($overdueCustomers as $customer) {
            // Get oldest unpaid invoice
            $oldestUnpaid = $customer->invoices()
                ->where('balance', '>', 0)
                ->where('status', 'active')
                ->orderBy('date', 'asc')
                ->first();

            $overdueSince = $oldestUnpaid?->date?->diffInDays(now()) ?? 0;

            // Check if alert already exists
            $existingAlert = AiAlert::where('model_type', Customer::class)
                ->where('model_id', $customer->id)
                ->where('type', 'overdue_customer')
                ->where('is_resolved', false)
                ->first();

            if (! $existingAlert) {
                $alert = AiAlert::create([
                    'type' => 'overdue_customer',
                    'severity' => $overdueSince > ($overdueDays * 2) ? 'critical' : 'warning',
                    'title' => 'عميل متأخر في السداد',
                    'message' => "العميل {$customer->name} لديه رصيد {$customer->balance} متأخر منذ {$overdueSince} يوم",
                    'data' => [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'balance' => $customer->balance,
                        'overdue_days' => $overdueSince,
                        'oldest_invoice_id' => $oldestUnpaid?->id,
                    ],
                    'model_type' => Customer::class,
                    'model_id' => $customer->id,
                ]);

                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(): array
    {
        return [
            'total_alerts' => AiAlert::count(),
            'unread_alerts' => AiAlert::unread()->count(),
            'unresolved_alerts' => AiAlert::unresolved()->count(),
            'critical_alerts' => AiAlert::critical()->unresolved()->count(),
            'by_type' => AiAlert::unresolved()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }
}
