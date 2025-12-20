<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\InventoryAdjustment;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * InventoryAdjustmentService
 *
 * Handles inventory corrections with Maker-Checker approval
 * All adjustments require approval before being applied
 */
class InventoryAdjustmentService
{

    /**
     * Create inventory adjustment request (pending approval)
     */
    public function createAdjustment(
        int $shipmentItemId,
        float $newQuantity,
        string $type,
        string $reason
    ): InventoryAdjustment {
        return DB::transaction(function () use ($shipmentItemId, $newQuantity, $type, $reason) {
            $item = ShipmentItem::findOrFail($shipmentItemId);

            // Validate: can't adjust settled shipment
            if ($item->shipment->status === 'settled') {
                throw new BusinessException(
                    'ADJ_001',
                    'لا يمكن تعديل مخزون شحنة مُصفاة',
                    'Cannot adjust inventory of a settled shipment'
                );
            }

            // Validate: new quantity can't be negative
            if ($newQuantity < 0) {
                throw new BusinessException(
                    'ADJ_002',
                    'الكمية لا يمكن أن تكون سالبة',
                    'Quantity cannot be negative'
                );
            }

            // Validate: can't reduce below sold cartons
            if ($newQuantity < $item->sold_cartons) {
                throw new BusinessException(
                    'ADJ_003',
                    'لا يمكن تقليل الكمية لأقل من المباع',
                    'Cannot reduce quantity below sold amount'
                );
            }

            // Calculate change based on cartons
            $currentCartons = $item->cartons;
            $quantityChange = $newQuantity - $currentCartons;

            $adjustment = InventoryAdjustment::create([
                'adjustment_number' => $this->generateNumber(),
                'shipment_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity_before' => $currentCartons,
                'quantity_after' => $newQuantity,
                'quantity_change' => $quantityChange,
                'adjustment_type' => $type,
                'reason' => $reason,
                'unit_cost' => $item->unit_cost,
                'total_cost_impact' => $quantityChange * $item->unit_cost,
                'status' => InventoryAdjustment::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]);

            AuditService::logAdjustment(
                'inventory_adjustment_created',
                $item,
                ['adjustment_id' => $adjustment->id, 'change' => $quantityChange]
            );

            return $adjustment;
        });
    }

    /**
     * Approve adjustment and apply changes
     */
    public function approve(InventoryAdjustment $adjustment, User $approver): void
    {
        if (!$adjustment->isPending()) {
            throw new BusinessException(
                'ADJ_004',
                'التسوية ليست في انتظار الموافقة',
                'Adjustment is not pending approval'
            );
        }

        if (!$adjustment->canBeApprovedBy($approver)) {
            throw new BusinessException(
                'ADJ_005',
                'لا يمكنك الموافقة على تسويتك الخاصة',
                'You cannot approve your own adjustment (Maker-Checker)'
            );
        }

        DB::transaction(function () use ($adjustment, $approver) {
            $item = $adjustment->shipmentItem;

            // Re-validate: shipment still not settled
            if ($item->shipment->status === 'settled') {
                throw new BusinessException(
                    'ADJ_001',
                    'الشحنة تم تصفيتها بعد إنشاء التسوية',
                    'Shipment was settled after adjustment was created'
                );
            }

            // Apply the change to cartons
            $item->cartons = $adjustment->quantity_after;
            $item->saveQuietly();

            // Update adjustment
            $adjustment->update([
                'status' => InventoryAdjustment::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            AuditService::logAdjustment(
                'inventory_adjustment_approved',
                $item,
                [
                    'adjustment_id' => $adjustment->id,
                    'approved_by' => $approver->id,
                    'quantity_before' => $adjustment->quantity_before,
                    'quantity_after' => $adjustment->quantity_after,
                ]
            );
        });
    }

    /**
     * Reject adjustment
     */
    public function reject(InventoryAdjustment $adjustment, User $rejector, string $reason): void
    {
        if (!$adjustment->isPending()) {
            throw new BusinessException(
                'ADJ_004',
                'التسوية ليست في انتظار الموافقة',
                'Adjustment is not pending approval'
            );
        }

        $adjustment->update([
            'status' => InventoryAdjustment::STATUS_REJECTED,
            'approved_by' => $rejector->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditService::logAdjustment(
            'inventory_adjustment_rejected',
            $adjustment->shipmentItem,
            ['adjustment_id' => $adjustment->id, 'reason' => $reason]
        );
    }

    /**
     * Get pending adjustments for approval
     */
    public function getPendingAdjustments()
    {
        return InventoryAdjustment::pending()
            ->with(['shipmentItem.shipment', 'product', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get adjustments history for a product
     */
    public function getProductHistory(int $productId)
    {
        return InventoryAdjustment::byProduct($productId)
            ->approved()
            ->with(['shipmentItem.shipment', 'createdBy', 'approvedBy'])
            ->orderBy('approved_at', 'desc')
            ->get();
    }

    /**
     * Generate unique adjustment number
     */
    private function generateNumber(): string
    {
        $date = now()->format('Ymd');
        $count = InventoryAdjustment::whereDate('created_at', today())->count() + 1;

        return "ADJ-{$date}-" . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
