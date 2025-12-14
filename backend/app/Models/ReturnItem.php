<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id',
        'product_id',
        'original_invoice_item_id',
        'target_shipment_item_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relationships
    public function return(): BelongsTo
    {
        return $this->belongsTo(ReturnModel::class, 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function originalInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class, 'original_invoice_item_id');
    }

    public function targetShipmentItem(): BelongsTo
    {
        return $this->belongsTo(ShipmentItem::class, 'target_shipment_item_id');
    }
}
