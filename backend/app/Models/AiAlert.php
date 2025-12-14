<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'severity',
        'title',
        'message',
        'data',
        'model_type',
        'model_id',
        'is_read',
        'is_resolved',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    // Mark as read
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    // Resolve alert
    public function resolve(?int $userId = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_by' => $userId ?? auth()->id(),
            'resolved_at' => now(),
        ]);
    }
}
