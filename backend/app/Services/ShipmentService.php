<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Carryover;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    /**
     * تصفية الشحنة وترحيل الكراتين المتبقية
     *
     * @param  Shipment  $shipment  الشحنة المراد تصفيتها
     * @param  Shipment  $nextShipment  الشحنة المفتوحة لاستقبال المتبقي
     *
     * @throws \Exception
     */
    public function settle(Shipment $shipment, Shipment $nextShipment): void
    {
        if ($shipment->status === 'settled') {
            throw new BusinessException('SHP_007');
        }

        if ($nextShipment->status !== 'open') {
            throw new BusinessException('SHP_004');
        }

        if ($shipment->id === $nextShipment->id) {
            throw new BusinessException('SHP_006');
        }

        DB::transaction(function () use ($shipment, $nextShipment) {
            // جلب الأصناف ذات كراتين متبقية
            $itemsWithRemaining = $shipment->items()
                ->get()
                ->filter(fn($item) => $item->remaining_cartons > 0);

            foreach ($itemsWithRemaining as $item) {
                $remainingCartons = $item->remaining_cartons;

                // البحث عن item موجود بنفس المنتج والوزن في الشحنة التالية
                $existingItem = $nextShipment->items()
                    ->where('product_id', $item->product_id)
                    ->where('weight_per_unit', $item->weight_per_unit)
                    ->first();

                if ($existingItem) {
                    // إضافة للـ item الموجود
                    $existingItem->increment('carryover_in_cartons', $remainingCartons);
                    $newItem = $existingItem;
                } else {
                    // إنشاء item جديد في الشحنة التالية
                    $newItem = ShipmentItem::create([
                        'shipment_id' => $nextShipment->id,
                        'product_id' => $item->product_id,
                        'weight_label' => $item->weight_label,
                        'weight_per_unit' => $item->weight_per_unit,
                        'cartons' => 0,  // لا توجد كراتين جديدة، فقط مرحلة
                        'sold_cartons' => 0,
                        'carryover_in_cartons' => $remainingCartons,
                        'carryover_out_cartons' => 0,
                        'unit_cost' => $item->unit_cost,
                    ]);
                }

                // إنشاء سجل الترحيل
                Carryover::create([
                    'from_shipment_id' => $shipment->id,
                    'from_shipment_item_id' => $item->id,
                    'to_shipment_id' => $nextShipment->id,
                    'to_shipment_item_id' => $newItem->id,
                    'product_id' => $item->product_id,
                    'cartons' => $remainingCartons,
                    'reason' => 'end_of_shipment',
                    'notes' => "ترحيل من شحنة {$shipment->number}",
                    'created_by' => auth()->id(),
                ]);

                // تحديث الـ item الأصلي
                $item->update([
                    'carryover_out_cartons' => $remainingCartons,
                ]);

                // حساب العجز: الوزن المتوقع - الوزن الفعلي المباع
                $expectedWeight = ($item->cartons + $item->carryover_in_cartons) * $item->weight_per_unit;
                $actualSoldWeight = $item->invoiceItems()->sum('quantity');
                $wastage = $expectedWeight - $actualSoldWeight;

                if ($wastage > 0) {
                    $item->update(['wastage_quantity' => $wastage]);
                }
            }

            // Calculate totals AFTER updating items
            // Calculate actual sales from invoice items (excluding cancelled invoices)
            $totalSales = \App\Models\InvoiceItem::whereIn('shipment_item_id', $shipment->items()->pluck('id'))
                ->whereHas('invoice', fn($q) => $q->where('status', '!=', 'cancelled'))
                ->sum('subtotal');

            $totalSoldCartons = $shipment->items()->sum('sold_cartons');
            $totalWastage = $shipment->items()->sum('wastage_quantity');
            $totalCarryoverOut = $shipment->items()->sum('carryover_out_cartons');

            // Get expenses linked to this shipment only
            $totalSupplierExpenses = \App\Models\Expense::where('shipment_id', $shipment->id)
                ->where('type', 'supplier')
                ->sum('amount');

            // Calculate financial summary for balance tracking
            $commissionRate = (float) (config('settings.company_commission_rate', 6)) / 100;
            $netSales = $totalSales;
            $companyCommission = $netSales * $commissionRate;

            // Get previous balance from last settled shipment OR supplier's opening balance
            $previousSettledShipment = Shipment::where('supplier_id', $shipment->supplier_id)
                ->where('id', '<', $shipment->id)
                ->where('status', 'settled')
                ->orderBy('id', 'desc')
                ->first();

            if ($previousSettledShipment) {
                // Use final balance from previous shipment
                $previousBalance = (float) ($previousSettledShipment->final_supplier_balance ?? 0);
            } else {
                // First shipment - use supplier's opening balance
                $previousBalance = (float) ($shipment->supplier->opening_balance ?? 0);
            }

            // Calculate final balance
            $finalBalance = $netSales - $companyCommission - $totalSupplierExpenses + $previousBalance;

            // تغيير حالة الشحنة
            $shipment->update([
                'status' => 'settled',
                'settled_at' => now(),
                'settled_by' => auth()->id(),
                'total_sales' => $totalSales,
                'total_wastage' => $totalWastage,
                'total_carryover_out' => $totalCarryoverOut,
                'total_supplier_expenses' => $totalSupplierExpenses,
                'previous_supplier_balance' => $previousBalance,
                'final_supplier_balance' => $finalBalance,
            ]);

            // Send Telegram notification
            $this->sendSettlementReportToTelegram($shipment->fresh());
        });
    }

    /**
     * Send settlement report PDF to Telegram
     */
    private function sendSettlementReportToTelegram(Shipment $shipment): void
    {
        try {
            $telegram = app(TelegramService::class);

            if (!$telegram->isConfigured()) {
                return;
            }

            // Generate PDF
            $pdfService = app(\App\Services\Reports\PdfGeneratorService::class);
            $reportService = app(\App\Services\Reports\ShipmentSettlementReportService::class);

            $data = $reportService->generate($shipment);
            $filename = "reports/settlement-{$shipment->number}.pdf";
            $path = $pdfService->save('reports.shipment-settlement', $data, $filename);

            // Send to Telegram
            $summary = [
                'total_sales' => $data['totalSales'] ?? 0,
                'commission' => $data['companyCommission'] ?? 0,
                'final_balance' => $data['finalSupplierBalance'] ?? 0,
            ];

            $telegram->sendSettlementReport($path, $shipment->number, $shipment->supplier->name, $summary);
        } catch (\Exception $e) {
            \Log::warning('Failed to send settlement report to Telegram', ['error' => $e->getMessage()]);
        }
    }

    /**
     * إلغاء تصفية الشحنة
     */
    public function unsettle(Shipment $shipment): void
    {
        if ($shipment->status !== 'settled') {
            throw new BusinessException('SHP_007');
        }

        DB::transaction(function () use ($shipment) {
            $carryovers = Carryover::where('from_shipment_id', $shipment->id)
                ->where('reason', 'end_of_shipment')
                ->with(['fromShipmentItem', 'toShipmentItem', 'toShipment'])
                ->get();

            foreach ($carryovers as $carryover) {
                $nextItem = $carryover->toShipmentItem;

                // Safety Check - لا يمكن إلغاء التصفية إذا تم بيع الكراتين المرحلة
                if ($nextItem && $nextItem->sold_cartons > 0) {
                    // Check if sold_cartons is from the carryover
                    $availableToReverse = $nextItem->carryover_in_cartons - $nextItem->sold_cartons;
                    if ($availableToReverse < $carryover->cartons) {
                        throw new BusinessException('SHP_005');
                    }
                }

                // استرجاع للشحنة الأصلية
                $carryover->fromShipmentItem->decrement('carryover_out_cartons', $carryover->cartons);

                // خصم من الشحنة التالية
                if ($nextItem) {
                    $nextItem->decrement('carryover_in_cartons', $carryover->cartons);

                    // حذف item إذا فارغ (لا كراتين أصلية ولا مرحلة)
                    if ($nextItem->cartons === 0 && $nextItem->carryover_in_cartons <= 0) {
                        $nextItem->delete();
                    }
                }

                // حذف سجل الترحيل
                $carryover->delete();
            }

            // Reset wastage
            $shipment->items()->update(['wastage_quantity' => 0]);

            // تغيير حالة الشحنة
            $shipment->update([
                'status' => 'closed',
                'settled_at' => null,
            ]);
        });
    }

    /**
     * إنشاء تقرير التصفية
     */
    public function generateSettlementReport(Shipment $shipment): array
    {
        $items = $shipment->items()->with('product')->get();

        $report = [
            'shipment' => [
                'id' => $shipment->id,
                'number' => $shipment->number,
                'supplier' => $shipment->supplier->name ?? null,
                'date' => $shipment->date ? $shipment->date->format('Y-m-d') : null,
                'status' => $shipment->status,
                'settled_at' => $shipment->settled_at ? $shipment->settled_at->format('Y-m-d H:i:s') : null,
            ],
            'items' => [],
            'totals' => [
                'total_cartons' => 0,
                'sold_cartons' => 0,
                'carryover_in' => 0,
                'carryover_out' => 0,
                'remaining_cartons' => 0,
                'expected_weight' => 0,
                'actual_sold_weight' => 0,
                'wastage' => 0,
                'total_cost' => 0,
            ],
        ];

        foreach ($items as $item) {
            $expectedWeight = ($item->cartons + $item->carryover_in_cartons) * $item->weight_per_unit;
            $actualSoldWeight = $item->invoiceItems()->sum('quantity');

            $itemData = [
                'product' => $item->product->name_en ?? $item->product->name,
                'weight_label' => $item->weight_label,
                'weight_per_unit' => (float) $item->weight_per_unit,
                'cartons' => $item->cartons,
                'sold_cartons' => $item->sold_cartons,
                'carryover_in' => $item->carryover_in_cartons,
                'carryover_out' => $item->carryover_out_cartons,
                'remaining_cartons' => $item->remaining_cartons,
                'expected_weight' => $expectedWeight,
                'actual_sold_weight' => (float) $actualSoldWeight,
                'wastage' => (float) $item->wastage_quantity,
                'unit_cost' => (float) $item->unit_cost,
                'total_cost' => (float) ($item->cartons * $item->unit_cost),
            ];

            $report['items'][] = $itemData;

            $report['totals']['total_cartons'] += $item->cartons;
            $report['totals']['sold_cartons'] += $item->sold_cartons;
            $report['totals']['carryover_in'] += $item->carryover_in_cartons;
            $report['totals']['carryover_out'] += $item->carryover_out_cartons;
            $report['totals']['remaining_cartons'] += $item->remaining_cartons;
            $report['totals']['expected_weight'] += $expectedWeight;
            $report['totals']['actual_sold_weight'] += $actualSoldWeight;
            $report['totals']['wastage'] += $item->wastage_quantity;
            $report['totals']['total_cost'] += $itemData['total_cost'];
        }

        return $report;
    }
}

