<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'severity',
        'title',
        'message',
        'data',
        'status',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'data' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }

    // Relations
    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // Methods
    public function acknowledge(?int $userId = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $userId ?? auth()->id(),
            'acknowledged_at' => now(),
        ]);
    }

    public function dismiss(): void
    {
        $this->update(['status' => 'dismissed']);
    }
}
