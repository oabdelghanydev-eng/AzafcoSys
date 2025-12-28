<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'balance',
        'opening_balance',
        'opening_balance_set_at',
        'opening_balance_set_by',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'opening_balance_set_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function cashboxTransactions(): HasMany
    {
        return $this->hasMany(CashboxTransaction::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCashbox($query)
    {
        return $query->where('type', 'cashbox');
    }

    public function scopeBank($query)
    {
        return $query->where('type', 'bank');
    }
}
