<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Carryover;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    /**
     * تصفية الشحنة وترحيل المتبقي
     *
     * @param  Shipment  $shipment  الشحنة المراد تصفيتها
     * @param  Shipment  $nextShipment  الشحنة المفتوحة لاستقبال المتبقي
     *
     * @throws \Exception
     */
    public function settle(Shipment $shipment, Shipment $nextShipment): void
    {
        if ($shipment->status === 'settled') {
            throw new BusinessException('SHP_007'); // Fixed: was SHP_003
        }

        if ($nextShipment->status !== 'open') {
            throw new BusinessException('SHP_004');
        }

        if ($shipment->id === $nextShipment->id) {
            throw new BusinessException('SHP_006');
        }

        DB::transaction(function () use ($shipment, $nextShipment) {
            // جلب الأصناف ذات المتبقي
            $itemsWithRemaining = $shipment->items()
                ->where('remaining_quantity', '>', 0)
                ->get();

            foreach ($itemsWithRemaining as $item) {
                // البحث عن item موجود بنفس المنتج والوزن في الشحنة التالية
                $existingItem = $nextShipment->items()
                    ->where('product_id', $item->product_id)
                    ->where('weight_per_unit', $item->weight_per_unit)
                    ->first();

                if ($existingItem) {
                    // إضافة للـ item الموجود
                    $existingItem->increment('initial_quantity', $item->remaining_quantity);
                    $existingItem->increment('remaining_quantity', $item->remaining_quantity);
                    $existingItem->increment('carryover_in_quantity', $item->remaining_quantity);
                    $newItem = $existingItem;
                } else {
                    // إنشاء item جديد في الشحنة التالية
                    $newItem = ShipmentItem::create([
                        'shipment_id' => $nextShipment->id,
                        'product_id' => $item->product_id,
                        'weight_label' => $item->weight_label,
                        'weight_per_unit' => $item->weight_per_unit,
                        'cartons' => 0, // ترحيل، لا يوجد كراتين جديدة
                        'initial_quantity' => $item->remaining_quantity,
                        'remaining_quantity' => $item->remaining_quantity,
                        'carryover_in_quantity' => $item->remaining_quantity,
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
                    'quantity' => $item->remaining_quantity,
                    'reason' => 'end_of_shipment',
                    'notes' => "ترحيل من شحنة {$shipment->number}",
                    'created_by' => auth()->id(),
                ]);

                // تحديث الـ item الأصلي
                $item->update([
                    'carryover_out_quantity' => $item->remaining_quantity,
                    'remaining_quantity' => 0,
                ]);
            }

            // Calculate totals AFTER updating items
            $totalSales = $shipment->items()->sum('sold_quantity');
            $totalWastage = $shipment->items()->sum('wastage_quantity');
            $totalCarryoverOut = $shipment->items()->sum('carryover_out_quantity');
            $totalSupplierExpenses = \App\Models\Expense::where('type', 'supplier')
                ->where('supplier_id', $shipment->supplier_id)
                ->sum('amount');

            // تغيير حالة الشحنة مع كل البيانات المطلوبة
            $shipment->update([
                'status' => 'settled',
                'settled_at' => now(),
                'settled_by' => auth()->id(),
                'total_sales' => $totalSales,
                'total_wastage' => $totalWastage,
                'total_carryover_out' => $totalCarryoverOut,
                'total_supplier_expenses' => $totalSupplierExpenses,
            ]);
        });
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

                // Safety Check - لا يمكن إلغاء التصفية إذا تم بيع المرحل
                if ($nextItem && $nextItem->remaining_quantity < $carryover->quantity) {
                    throw new BusinessException('SHP_005');
                }

                // استرجاع للشحنة الأصلية
                $carryover->fromShipmentItem->increment(
                    'remaining_quantity',
                    (float) $carryover->quantity
                );
                $carryover->fromShipmentItem->decrement(
                    'carryover_out_quantity',
                    (float) $carryover->quantity
                );

                // خصم من الشحنة التالية
                if ($nextItem) {
                    $nextItem->decrement('initial_quantity', (float) $carryover->quantity);
                    $nextItem->decrement('remaining_quantity', (float) $carryover->quantity);
                    $nextItem->decrement('carryover_in_quantity', (float) $carryover->quantity);

                    // حذف item إذا فارغ
                    if ($nextItem->initial_quantity <= 0) {
                        $nextItem->delete();
                    }
                }

                // حذف سجل الترحيل
                $carryover->delete();
            }

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
                'initial' => 0,
                'sold' => 0,
                'wastage' => 0,
                'carryover_in' => 0,
                'carryover_out' => 0,
                'remaining' => 0,
                'total_cost' => 0,
                'total_sales' => 0,
            ],
        ];

        foreach ($items as $item) {
            $itemData = [
                'product' => $item->product->name,
                'weight_label' => $item->weight_label,
                'cartons' => $item->cartons,
                'initial' => (float) $item->initial_quantity,
                'sold' => (float) $item->sold_quantity,
                'wastage' => (float) $item->wastage_quantity,
                'carryover_in' => (float) ($item->carryover_in_quantity ?? 0),
                'carryover_out' => (float) ($item->carryover_out_quantity ?? 0),
                'remaining' => (float) $item->remaining_quantity,
                'unit_cost' => (float) $item->unit_cost,
                'total_cost' => (float) ($item->initial_quantity * $item->unit_cost),
            ];

            $report['items'][] = $itemData;

            $report['totals']['initial'] += $itemData['initial'];
            $report['totals']['sold'] += $itemData['sold'];
            $report['totals']['wastage'] += $itemData['wastage'];
            $report['totals']['carryover_in'] += $itemData['carryover_in'];
            $report['totals']['carryover_out'] += $itemData['carryover_out'];
            $report['totals']['remaining'] += $itemData['remaining'];
            $report['totals']['total_cost'] += $itemData['total_cost'];
        }

        return $report;
    }
}
