<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'phone',
        'address',
        'balance',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnModel::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithDebt($query)
    {
        return $query->where('balance', '>', 0);
    }

    // Accessors
    public function getFormattedBalanceAttribute(): string
    {
        $balance = (float) $this->balance;
        $prefix = $balance >= 0 ? 'مديون' : 'دائن';
        return number_format(abs($balance), 2) . ' ج.م (' . $prefix . ')';
    }
}
