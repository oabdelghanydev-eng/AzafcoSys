<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'invoice_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
