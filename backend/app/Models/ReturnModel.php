<?php

namespace App\Models;

use App\Exceptions\BusinessException;
use App\Traits\ChecksClosedDailyReport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnModel extends Model
{
    use HasFactory;
    use ChecksClosedDailyReport;

    /**
     * Flag to indicate cancellation is via ReturnService (authorized path)
     */
    public bool $cancelViaService = false;

    /**
     * Boot the model.
     * 
     * SEV-1 FIX (2025-12-27):
     * Prevent direct status change to 'cancelled' which bypasses ledger reversal.
     * All cancellations MUST go through ReturnService::cancelReturn()
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function (ReturnModel $return) {
            if ($return->isDirty('status')) {
                $oldStatus = $return->getOriginal('status');
                $newStatus = $return->status;

                // Guard: Block direct cancellation bypass
                if ($oldStatus === 'active' && $newStatus === 'cancelled') {
                    if (!$return->cancelViaService) {
                        throw new BusinessException(
                            'RET_BYPASS',
                            'يجب إلغاء المرتجع عبر الخدمة المخصصة فقط',
                            'Return cancellation must use ReturnService::cancelReturn(). Direct model update not allowed - ledger would not be reversed.'
                        );
                    }
                }
            }
        });
    }

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'customer_id',
        'original_invoice_id',
        'date',
        'total_amount',
        'status',
        'notes',
        'created_by',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
