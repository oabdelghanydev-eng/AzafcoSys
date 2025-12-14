<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Correction Model
 * 
 * Tracks all corrections with full audit trail
 * Implements Maker-Checker approval workflow
 */
class Correction extends Model
{
    use HasFactory;

    protected $fillable = [
        'correctable_type',
        'correctable_id',
        'correction_type',
        'original_value',
        'adjustment_value',
        'new_value',
        'reason',
        'reason_code',
        'notes',
        'correction_sequence',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
        'adjustment_value' => 'decimal:2',
        'new_value' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Correction types
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_REVERSAL = 'reversal';
    const TYPE_REALLOCATION = 'reallocation';

    // Status
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // Relationships
    public function correctable(): MorphTo
    {
        return $this->morphTo();
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

    // Helpers
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeApprovedBy(User $user): bool
    {
        // Maker-Checker: approver must be different from creator
        return $user->id !== $this->created_by
            && $user->hasPermission('corrections.approve');
    }
}
