<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'product_id',
        'weight_per_unit',
        'weight_label',
        'cartons',
        'initial_quantity',
        'sold_quantity',
        'remaining_quantity',
        'wastage_quantity',
        'carryover_in_quantity',
        'carryover_out_quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'weight_per_unit' => 'decimal:3',
        'initial_quantity' => 'decimal:3',
        'sold_quantity' => 'decimal:3',
        'remaining_quantity' => 'decimal:3',
        'wastage_quantity' => 'decimal:3',
        'carryover_in_quantity' => 'decimal:3',
        'carryover_out_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // Relationships
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'target_shipment_item_id');
    }

    // Scopes
    public function scopeWithStock($query)
    {
        return $query->where('remaining_quantity', '>', 0);
    }

    public function scopeForFifo($query, int $productId)
    {
        return $query->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)
            ->whereHas('shipment', fn ($q) => $q->whereIn('status', ['open', 'closed']))
            ->orderBy('created_at', 'asc');
    }

    // Check if has available stock
    public function hasStock(): bool
    {
        return $this->remaining_quantity > 0;
    }

    // Get available quantity for sale
    public function getAvailableQuantity(): float
    {
        return max(0, $this->remaining_quantity);
    }
}
