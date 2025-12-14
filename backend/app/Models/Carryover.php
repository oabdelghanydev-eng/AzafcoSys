<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Carryover extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_shipment_id',
        'from_shipment_item_id',
        'to_shipment_id',
        'to_shipment_item_id',
        'product_id',
        'quantity',
        'reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    // Relationships
    public function fromShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'from_shipment_id');
    }

    public function fromShipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class, 'from_shipment_item_id');
    }

    public function toShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'to_shipment_id');
    }

    public function toShipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class, 'to_shipment_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
