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
        'sold_cartons',
        'carryover_in_cartons',
        'carryover_out_cartons',
        'wastage_quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'weight_per_unit' => 'decimal:3',
        'cartons' => 'integer',
        'sold_cartons' => 'integer',
        'carryover_in_cartons' => 'integer',
        'carryover_out_cartons' => 'integer',
        'wastage_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    protected $appends = ['remaining_cartons', 'expected_weight'];

    // ============ Relationships ============

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

    // ============ Accessors (Computed) ============

    /**
     * الكراتين المتبقية للبيع
     * = الكراتين الأصلية + المرحل إليها - المباعة - المرحل منها
     */
    public function getRemainingCartonsAttribute(): int
    {
        return $this->cartons
            + $this->carryover_in_cartons
            - $this->sold_cartons
            - $this->carryover_out_cartons;
    }

    /**
     * الوزن المتوقع للكراتين المتبقية
     */
    public function getExpectedWeightAttribute(): float
    {
        return $this->remaining_cartons * (float) $this->weight_per_unit;
    }

    /**
     * الوزن الفعلي المباع (من الميزان)
     */
    public function getActualSoldWeightAttribute(): float
    {
        return (float) $this->invoiceItems()->sum('quantity');
    }

    /**
     * إجمالي الكراتين (الأصلية + المرحلة إليها)
     */
    public function getTotalCartonsAttribute(): int
    {
        return $this->cartons + $this->carryover_in_cartons;
    }

    // ============ Scopes ============

    public function scopeWithStock($query)
    {
        return $query->whereRaw(
            '(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) > 0'
        );
    }

    public function scopeForFifo($query, int $productId)
    {
        return $query->where('product_id', $productId)
            ->whereRaw('(cartons + carryover_in_cartons - sold_cartons - carryover_out_cartons) > 0')
            ->whereHas('shipment', fn($q) => $q->whereIn('status', ['open', 'closed']))
            ->orderBy('created_at', 'asc');
    }

    // ============ Helper Methods ============

    public function hasStock(): bool
    {
        return $this->remaining_cartons > 0;
    }

    public function getAvailableCartons(): int
    {
        return max(0, $this->remaining_cartons);
    }
}

