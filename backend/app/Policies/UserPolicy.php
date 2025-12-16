<?php

namespace App\Policies;

use App\Models\User;

/**
 * UserPolicy
 *
 * Authorization rules for user management
 */
class UserPolicy
{
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    /**
     * Determine if the user can view a specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view');
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    /**
     * Determine if the user can update a user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.edit');
    }

    /**
     * Determine if the user can delete a user.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete self
        if ($user->id === $model->id) {
            return false;
        }

        return $user->hasPermission('users.delete');
    }

    /**
     * Determine if the user can unlock accounts.
     */
    public function unlock(User $user, User $model): bool
    {
        return $user->hasPermission('users.unlock');
    }
}
