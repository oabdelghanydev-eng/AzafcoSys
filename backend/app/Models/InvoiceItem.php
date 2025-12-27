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
 * - quantity (decimal)  = Actual weight from scale in kg (MISLEADING NAME!)
 * - unit_price (decimal) = Price per kg
 * - subtotal (decimal)  = quantity × unit_price
 *
 * When writing queries:
 * - Use SUM(cartons) for "Quantity" (carton count)
 * - Use SUM(quantity) for "Weight" (kg)
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $product_id
 * @property int $shipment_item_id
 * @property int $cartons Number of cartons sold
 * @property float $quantity Actual weight in kg (NOT carton count!)
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
        'quantity',   // ⚠️ ADR-001: This is WEIGHT (kg), not carton count!
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
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
}
