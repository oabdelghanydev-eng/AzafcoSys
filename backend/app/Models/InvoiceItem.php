<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InvoiceItem Model
 *
 * ⚠️ ADR-001: IMPORTANT COLUMN NAMING CONVENTION ⚠️
 *
 * The `quantity` column stores WEIGHT (kg), NOT carton count!
 * This is a legacy naming issue documented in ADR-001.
 *
 * Column Meanings:
 * - cartons (int)       = Number of cartons sold
 * - cartons_returned (int) = Cartons already returned
 * - quantity (decimal)  = Actual weight from scale in kg (MISLEADING NAME!)
 * - quantity_returned (decimal) = Weight already returned in kg
 * - unit_price (decimal) = Price per kg
 * - subtotal (decimal)  = quantity × unit_price
 *
 * INVENTORY LOGIC:
 * - Cartons are used for inventory tracking (sold_cartons)
 * - Quantity (weight) is used for pricing
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $product_id
 * @property int $shipment_item_id
 * @property int $cartons Number of cartons sold
 * @property int $cartons_returned Cartons already returned
 * @property float $quantity Actual weight in kg (NOT carton count!)
 * @property float $quantity_returned Weight already returned in kg
 * @property float $unit_price Price per kg
 * @property float $subtotal Total value
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'shipment_item_id',
        'cartons',
        'cartons_returned',     // Cartons already returned
        'quantity',             // ⚠️ ADR-001: This is WEIGHT (kg), not carton count!
        'quantity_returned',    // Weight already returned
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'cartons' => 'integer',
        'cartons_returned' => 'integer',
        'quantity' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class);
    }

    // Calculate subtotal
    public function calculateSubtotal(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Get remaining CARTONS that can still be returned
     * Used for inventory tracking
     */
    public function getRemainingReturnableCartons(): int
    {
        return max(0, $this->cartons - ($this->cartons_returned ?? 0));
    }

    /**
     * Get remaining WEIGHT that can still be returned (kg)
     * Used for pricing calculation
     */
    public function getRemainingReturnable(): float
    {
        return max(0, (float) $this->quantity - (float) ($this->quantity_returned ?? 0));
    }

    /**
     * Check if this item can accept a return (carton-based validation)
     */
    public function canReturn(int $cartons, float $quantity): bool
    {
        return $cartons > 0
            && $cartons <= $this->getRemainingReturnableCartons()
            && $quantity > 0
            && $quantity <= $this->getRemainingReturnable();
    }
}


