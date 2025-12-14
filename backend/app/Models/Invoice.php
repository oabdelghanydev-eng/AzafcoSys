<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'date',
        'type',
        'status',
        'subtotal',
        'discount',
        'total',
        'paid_amount',
        'balance',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('balance', '>', 0);
    }

    public function scopeFullyPaid($query)
    {
        return $query->where('balance', 0);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFullyPaid(): bool
    {
        return $this->balance <= 0;
    }

    public function hasPayments(): bool
    {
        return $this->paid_amount > 0;
    }

    // Recalculate totals from items
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('subtotal');
        $discount = (float) $this->discount;
        $paidAmount = (float) $this->paid_amount;

        $this->subtotal = $subtotal;
        $this->total = $subtotal - $discount;
        $this->balance = ($subtotal - $discount) - $paidAmount;
        $this->saveQuietly();
    }
}
