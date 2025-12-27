<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DailyReport Model
 *
 * Represents a daily financial report.
 * All users work on the same daily report.
 */
class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'status',
        'cashbox_opening',
        'bank_opening',
        'total_sales',
        'total_collections_cash',
        'total_collections_bank',
        'total_collections',
        'total_expenses',
        'total_expenses_cash',
        'total_expenses_bank',
        'total_wastage',
        'total_transfers_in',
        'total_transfers_out',
        'cash_balance',
        'bank_balance',
        'cashbox_closing',
        'bank_closing',
        'cashbox_difference',
        'net_day',
        'invoices_count',
        'collections_count',
        'expenses_count',
        'notes',
        'created_by',
        'opened_by',
        'closed_at',
        'closed_by',
        'force_close_reason',
        'reopened_at',
        'reopened_by',
        'ai_alerts',
    ];

    protected $casts = [
        'date' => 'date',
        'cashbox_opening' => 'decimal:2',
        'bank_opening' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_collections_cash' => 'decimal:2',
        'total_collections_bank' => 'decimal:2',
        'total_expenses_cash' => 'decimal:2',
        'total_expenses_bank' => 'decimal:2',
        'total_wastage' => 'decimal:2',
        'cashbox_closing' => 'decimal:2',
        'bank_closing' => 'decimal:2',
        'cashbox_difference' => 'decimal:2',
        'net_day' => 'decimal:2',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'ai_alerts' => 'array',
    ];

    // Status constants
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    // Relationships
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    // Accessors
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    // Get formatted date
    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('Y-m-d');
    }
}
