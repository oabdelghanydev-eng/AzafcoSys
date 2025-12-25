<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'supplier_id',
        'date',
        'status',
        'total_cost',
        'notes',
        'settled_at',
        'settled_by',
        'created_by',
        // Settlement totals
        'total_sales',
        'total_wastage',
        'total_carryover_out',
        'total_supplier_expenses',
        // Balance tracking for reporting chain
        'previous_supplier_balance',
        'final_supplier_balance',
        // fifo_sequence is NOT fillable - it's auto-generated and immutable
    ];

    protected $casts = [
        'date' => 'date',
        'total_cost' => 'decimal:2',
        'settled_at' => 'datetime',
        'fifo_sequence' => 'integer',
    ];

    /**
     * Boot method - auto-generate fifo_sequence on creation
     * Best Practice: fifo_sequence is immutable and used for FIFO ordering
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Shipment $shipment) {
            // Generate next fifo_sequence
            $maxSequence = static::max('fifo_sequence') ?? 0;
            $shipment->fifo_sequence = $maxSequence + 1;
        });
    }

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function carryoversFrom(): HasMany
    {
        return $this->hasMany(Carryover::class, 'from_shipment_id');
    }

    public function carryoversTo(): HasMany
    {
        return $this->hasMany(Carryover::class, 'to_shipment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    // Check if shipment can be modified
    public function isEditable(): bool
    {
        return $this->status !== 'settled';
    }

    // Check if all items are sold (cartons-based)
    public function isFullySold(): bool
    {
        return $this->items->every(fn($item) => $item->remaining_cartons <= 0);
    }
}
