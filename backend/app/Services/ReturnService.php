<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnItem;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Create a return linked to an original invoice.
     * 
     * Validates that:
     * - Invoice exists and is active
     * - Invoice belongs to the customer
     * - Each item's cartons don't exceed remaining returnable cartons
     * - Each item's quantity doesn't exceed remaining returnable weight
     * 
     * Updates:
     * - invoice_items.cartons_returned (for inventory)
     * - invoice_items.quantity_returned (for pricing)
     * - customer.balance (decreases)
     * - ShipmentItem.sold_cartons (decreases = inventory restoration)
     *
     * @param int $customerId Customer ID
     * @param array $items [{product_id, cartons, quantity, unit_price}]
     * @param int $originalInvoiceId Required invoice ID for validation
     * @param string|null $notes Optional notes
     */
    public function createReturn(
        int $customerId,
        array $items,
        int $originalInvoiceId,
        ?string $notes = null
    ): ReturnModel {

        return DB::transaction(function () use ($customerId, $items, $originalInvoiceId, $notes) {
            // Get open daily report
            $dailyReport = $this->dailyReportService->ensureOpenReport();
            $workingDate = $dailyReport->date;

            // Validate invoice
            $invoice = Invoice::with('items.product', 'items.shipmentItem')->find($originalInvoiceId);
            if (!$invoice) {
                throw new BusinessException('RET_003', 'Invoice not found', 'Invoice not found');
            }
            if ($invoice->customer_id !== $customerId) {
                throw new BusinessException('RET_004', 'Invoice does not belong to customer', 'Invoice mismatch');
            }
            if ($invoice->status === 'cancelled') {
                throw new BusinessException('RET_005', 'Cannot return on cancelled invoice', 'Invoice cancelled');
            }

            // Validate and prepare items
            $validatedItems = [];
            $totalAmount = 0;

            foreach ($items as $itemData) {
                $productId = $itemData['product_id'];
                $returnCartons = (int) $itemData['cartons'];
                $returnQuantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];

                // Find matching invoice item
                $invoiceItem = $invoice->items->first(function ($item) use ($productId, $unitPrice) {
                    return $item->product_id === $productId
                        && abs($item->unit_price - $unitPrice) < 0.01;
                });

                if (!$invoiceItem) {
                    throw new BusinessException(
                        'RET_006',
                        "Product not found in invoice or price mismatch",
                        "Product #{$productId} not in invoice"
                    );
                }

                // Check remaining returnable CARTONS (for inventory)
                $remainingCartons = $invoiceItem->getRemainingReturnableCartons();
                if ($returnCartons > $remainingCartons) {
                    throw new BusinessException(
                        'RET_007',
                        "Return cartons {$returnCartons} exceeds remaining {$remainingCartons}",
                        "Cartons exceed returnable"
                    );
                }

                // Check remaining returnable WEIGHT (for pricing)
                $remainingWeight = $invoiceItem->getRemainingReturnable();
                if ($returnQuantity > $remainingWeight) {
                    throw new BusinessException(
                        'RET_008',
                        "Return weight {$returnQuantity}kg exceeds remaining {$remainingWeight}kg",
                        "Weight exceeds returnable"
                    );
                }

                $subtotal = $returnQuantity * $unitPrice;
                $totalAmount += $subtotal;

                $validatedItems[] = [
                    'invoice_item' => $invoiceItem,
                    'cartons' => $returnCartons,
                    'quantity' => $returnQuantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ];
            }

            // Create return record
            $return = ReturnModel::create([
                'return_number' => $this->numberGenerator->generate('return'),
                'customer_id' => $customerId,
                'original_invoice_id' => $originalInvoiceId,
                'date' => $workingDate,
                'total_amount' => $totalAmount,
                'status' => 'active',
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Create return items and update tracking
            foreach ($validatedItems as $itemInfo) {
                $invoiceItem = $itemInfo['invoice_item'];

                // Get target shipment item for inventory restoration
                $targetShipmentItem = $this->getTargetShipmentItem(
                    $invoiceItem->product_id,
                    $invoiceItem->shipment_item_id
                );

                // Create return item
                ReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => $invoiceItem->product_id,
                    'original_invoice_item_id' => $invoiceItem->id,
                    'target_shipment_item_id' => $targetShipmentItem->id,
                    'cartons' => $itemInfo['cartons'],
                    'quantity' => $itemInfo['quantity'],
                    'unit_price' => $itemInfo['unit_price'],
                    'subtotal' => $itemInfo['subtotal'],
                ]);

                // Update invoice item tracking
                $invoiceItem->increment('cartons_returned', $itemInfo['cartons']);
                $invoiceItem->increment('quantity_returned', $itemInfo['quantity']);

                // 🔑 CRITICAL: Restore inventory (decrement sold_cartons)
                // This makes the cartons available for sale again
                $targetShipmentItem->decrement('sold_cartons', $itemInfo['cartons']);

                Log::info('[ReturnService] Return item processed', [
                    'invoice_item_id' => $invoiceItem->id,
                    'return_id' => $return->id,
                    'cartons_returned' => $itemInfo['cartons'],
                    'quantity_returned' => $itemInfo['quantity'],
                    'shipment_item_id' => $targetShipmentItem->id,
                    'user_id' => auth()->id(),
                ]);
            }

            // Decrease customer balance (refund)
            Customer::where('id', $customerId)->decrement('balance', $totalAmount);

            Log::info('[ReturnService] Return created', [
                'return_id' => $return->id,
                'return_number' => $return->return_number,
                'customer_id' => $customerId,
                'invoice_id' => $originalInvoiceId,
                'total_amount' => $totalAmount,
                'items_count' => count($validatedItems),
                'user_id' => auth()->id(),
            ]);

            return $return->fresh('items.product');
        });
    }

    /**
     * Get target shipment item for return inventory
     */
    private function getTargetShipmentItem(int $productId, ?int $preferredShipmentItemId = null): ShipmentItem
    {
        // If original shipment item provided, check if still open/closed
        if ($preferredShipmentItemId) {
            $item = ShipmentItem::with('shipment')->find($preferredShipmentItemId);

            if ($item && in_array($item->shipment->status, ['open', 'closed'])) {
                return $item;
            }

            // Settled shipment - do Late Return to current open shipment
            if ($item) {
                return $this->processLateReturn($item, $productId);
            }
        }

        // Find any open shipment item for this product
        $openItem = ShipmentItem::whereHas('shipment', fn($q) => $q->where('status', 'open'))
            ->where('product_id', $productId)
            ->first();

        if ($openItem) {
            return $openItem;
        }

        // Create new shipment item in open shipment
        return $this->createNewShipmentItem($productId);
    }

    /**
     * Process Late Return - item from settled shipment goes to current open shipment
     */
    private function processLateReturn(ShipmentItem $originalItem, int $productId): ShipmentItem
    {
        $openShipment = Shipment::where('status', 'open')->first();

        if (!$openShipment) {
            throw new BusinessException(
                'RET_001',
                'No open shipment available for return',
                'No open shipment'
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
        $openShipment = Shipment::where('status', 'open')->first();

        if (!$openShipment) {
            throw new BusinessException(
                'RET_001',
                'No open shipment available for return',
                'No open shipment'
            );
        }

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
        if ($return->status === 'cancelled') {
            throw new BusinessException('RET_009', 'Return already cancelled', 'Already cancelled');
        }

        DB::transaction(function () use ($return) {
            // Reverse all tracking
            foreach ($return->items as $item) {
                // Reverse invoice item tracking
                if ($item->original_invoice_item_id) {
                    InvoiceItem::where('id', $item->original_invoice_item_id)
                        ->decrement('cartons_returned', $item->cartons);
                    InvoiceItem::where('id', $item->original_invoice_item_id)
                        ->decrement('quantity_returned', $item->quantity);
                }

                // 🔑 CRITICAL: Reverse inventory restoration (re-increment sold_cartons)
                if ($item->target_shipment_item_id) {
                    ShipmentItem::where('id', $item->target_shipment_item_id)
                        ->increment('sold_cartons', $item->cartons);
                }

                Log::info('[ReturnService] Reversed return item', [
                    'return_item_id' => $item->id,
                    'return_id' => $return->id,
                    'cartons_reversed' => $item->cartons,
                    'quantity_reversed' => $item->quantity,
                    'user_id' => auth()->id(),
                ]);
            }

            // Reverse customer balance (re-increment)
            Customer::where('id', $return->customer_id)
                ->increment('balance', (float) $return->total_amount);

            // Mark as cancelled
            $return->cancelViaService = true;
            $return->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);

            Log::warning('[ReturnService] Return cancelled', [
                'return_id' => $return->id,
                'return_number' => $return->return_number,
                'customer_id' => $return->customer_id,
                'total_amount' => $return->total_amount,
                'user_id' => auth()->id(),
            ]);
        });
    }
}
