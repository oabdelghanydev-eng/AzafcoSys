<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\ReturnItem;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    private NumberGeneratorService $numberGenerator;
    private DailyReportService $dailyReportService;

    public function __construct(
        NumberGeneratorService $numberGenerator,
        DailyReportService $dailyReportService
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->dailyReportService = $dailyReportService;
    }

    /**
     * Create a return and update inventory + customer balance
     *
     * @param  array  $items  [{product_id, cartons, unit_price, shipment_item_id?, original_invoice_item_id?}]
     */
    public function createReturn(
        int $customerId,
        array $items,
        ?int $originalInvoiceId = null,
        ?string $notes = null
    ): ReturnModel {
        return DB::transaction(function () use ($customerId, $items, $originalInvoiceId, $notes) {
            // Get open daily report (throws BusinessException if none open)
            $dailyReport = $this->dailyReportService->ensureOpenReport();
            $workingDate = $dailyReport->date;

            // Calculate total (cartons * unit_price per carton)
            $totalAmount = collect($items)->sum(fn($item) => $item['cartons'] * $item['unit_price']);

            // Create return with working date from daily report
            $return = ReturnModel::create([
                'return_number' => $this->numberGenerator->generate('return'),
                'customer_id' => $customerId,
                'original_invoice_id' => $originalInvoiceId,
                'date' => $workingDate,  // Use daily report date
                'total_amount' => $totalAmount,
                'status' => 'active',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Process each item
            foreach ($items as $itemData) {
                $targetShipmentItem = $this->getTargetShipmentItem(
                    $itemData['product_id'],
                    $itemData['shipment_item_id'] ?? null
                );

                $cartons = (int) $itemData['cartons'];
                $weightPerUnit = $targetShipmentItem->weight_per_unit;
                $actualWeight = $cartons * $weightPerUnit;

                // Create return item with proper cartons and weight
                ReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => $itemData['product_id'],
                    'original_invoice_item_id' => $itemData['original_invoice_item_id'] ?? null,
                    'target_shipment_item_id' => $targetShipmentItem->id,
                    'cartons' => $cartons,
                    'quantity' => $actualWeight,  // Actual weight = cartons * weight_per_unit
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $cartons * $itemData['unit_price'],
                ]);

                // Restore inventory (decrement sold_cartons to "return" cartons to stock)
                $targetShipmentItem->decrement('sold_cartons', $cartons);
            }

            // Decrease customer balance
            Customer::where('id', $customerId)
                ->decrement('balance', $totalAmount);

            return $return->fresh('items');
        });
    }

    /**
     * Get target shipment item for return
     * If original is from settled shipment, use current open shipment (Late Return)
     */
    private function getTargetShipmentItem(int $productId, ?int $preferredShipmentItemId = null): ShipmentItem
    {
        // If specific shipment item provided, check if it's still open/closed
        if ($preferredShipmentItemId) {
            $item = ShipmentItem::with('shipment')->find($preferredShipmentItemId);

            if ($item && in_array($item->shipment->status, ['open', 'closed'])) {
                return $item;
            }

            // It's settled - need to do Late Return
            return $this->processLateReturn($item, $productId);
        }

        // Find any open shipment item for this product
        $openItem = ShipmentItem::whereHas('shipment', fn($q) => $q->where('status', 'open'))
            ->where('product_id', $productId)
            ->first();

        if ($openItem) {
            return $openItem;
        }

        // If no open shipment item, create one in current open shipment
        return $this->createNewShipmentItem($productId);
    }

    /**
     * Process Late Return - item from settled shipment goes to current open shipment
     */
    private function processLateReturn(ShipmentItem $originalItem, int $productId): ShipmentItem
    {
        // Find or create open shipment
        $openShipment = Shipment::where('status', 'open')->first();

        if (!$openShipment) {
            throw new BusinessException(
                'RET_001',
                'لا توجد شحنة مفتوحة لاستقبال المرتجع',
                'No open shipment available for return'
            );
        }

        // Find existing item or create new one
        $targetItem = ShipmentItem::where('shipment_id', $openShipment->id)
            ->where('product_id', $productId)
            ->where('weight_per_unit', $originalItem->weight_per_unit)
            ->first();

        if (!$targetItem) {
            $targetItem = ShipmentItem::create([
                'shipment_id' => $openShipment->id,
                'product_id' => $productId,
                'weight_per_unit' => $originalItem->weight_per_unit,
                'weight_label' => $originalItem->weight_label,
                'cartons' => 0,
                'sold_cartons' => 0,
                'carryover_in_cartons' => 0,
                'carryover_out_cartons' => 0,
                'unit_cost' => $originalItem->unit_cost,
            ]);
        }

        return $targetItem;
    }

    /**
     * Create new shipment item in open shipment for return
     */
    private function createNewShipmentItem(int $productId): ShipmentItem
    {
        $openShipment = Shipment::where('status', 'open')->firstOrFail();

        return ShipmentItem::create([
            'shipment_id' => $openShipment->id,
            'product_id' => $productId,
            'weight_per_unit' => 1,
            'cartons' => 0,
            'sold_cartons' => 0,
        ]);
    }

    /**
     * Cancel a return and reverse its effects
     */
    public function cancelReturn(ReturnModel $return): void
    {
        DB::transaction(function () use ($return) {
            // Reverse inventory changes (re-increment sold_cartons)
            foreach ($return->items as $item) {
                if ($item->targetShipmentItem) {
                    $item->targetShipmentItem->increment('sold_cartons', $item->cartons);
                }
            }

            // Reverse customer balance
            Customer::where('id', $return->customer_id)
                ->increment('balance', (float) $return->total_amount);

            // Mark as cancelled (set authorization flag to bypass guard)
            $return->cancelViaService = true;
            $return->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
        });
    }
}
