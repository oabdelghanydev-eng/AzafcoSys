<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'customer_id',
        'date',
        'amount',
        'payment_method',
        'distribution_method',
        'status',                   // Critical for cancellation Observer
        'allocated_amount',
        'unallocated_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CollectionAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Check if fully allocated
    public function isFullyAllocated(): bool
    {
        return $this->unallocated_amount <= 0;
    }

    // Recalculate allocation totals
    public function recalculateAllocations(): void
    {
        $allocatedAmount = (float) $this->allocations()->sum('amount');
        $amount = (float) $this->amount;

        $this->allocated_amount = $allocatedAmount;
        $this->unallocated_amount = $amount - $allocatedAmount;
        $this->saveQuietly();
    }
}
