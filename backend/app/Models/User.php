<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'permissions',
        'is_admin',
        'failed_login_attempts',
        'is_locked',
        'locked_at',
        'locked_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_admin' => 'boolean',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    // Check if user has permission
    public function hasPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    // Check if user has any of the permissions
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return ! empty(array_intersect($permissions, $this->permissions ?? []));
    }

    // Lock the account
    public function lock(?int $lockedBy = null): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $lockedBy,
        ]);
    }

    // Unlock the account
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'failed_login_attempts' => 0,
        ]);
    }

    // Increment failed login attempts
    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= 3) {
            $this->lock();
        }
    }
}
