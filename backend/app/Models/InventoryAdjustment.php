<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InventoryAdjustment Model
 *
 * Tracks inventory corrections with Maker-Checker approval
 * - Physical count differences
 * - Damage/wastage
 * - Error corrections
 */
class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'adjustment_number',
        'shipment_item_id',
        'product_id',
        'quantity_before',
        'quantity_after',
        'quantity_change',
        'adjustment_type',
        'reason',
        'unit_cost',
        'total_cost_impact',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'quantity_before' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'quantity_change' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost_impact' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Adjustment types
    const TYPE_PHYSICAL_COUNT = 'physical_count';

    const TYPE_DAMAGE = 'damage';

    const TYPE_THEFT = 'theft';

    const TYPE_ERROR = 'error';

    const TYPE_TRANSFER = 'transfer';

    const TYPE_EXPIRY = 'expiry';

    // Status
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    // Relationships
    public function shipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isIncrease(): bool
    {
        return $this->quantity_change > 0;
    }

    public function isDecrease(): bool
    {
        return $this->quantity_change < 0;
    }

    public function canBeApprovedBy(User $user): bool
    {
        // Maker-Checker: approver must be different from creator
        return $user->id !== $this->created_by
            && $user->hasPermission('inventory.adjust');
    }

    /**
     * Get type label in Arabic
     */
    public function getTypeLabel(): string
    {
        return match ($this->adjustment_type) {
            self::TYPE_PHYSICAL_COUNT => 'جرد فعلي',
            self::TYPE_DAMAGE => 'تالف',
            self::TYPE_THEFT => 'فقد/سرقة',
            self::TYPE_ERROR => 'تصحيح خطأ',
            self::TYPE_TRANSFER => 'نقل',
            self::TYPE_EXPIRY => 'انتهاء صلاحية',
            default => $this->adjustment_type,
        };
    }
}
