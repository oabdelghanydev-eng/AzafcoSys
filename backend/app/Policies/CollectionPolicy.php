<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use App\Models\Setting;

/**
 * Collection Policy
 * تحديث 2025-12-14: تفعيل Permission checks
 */
class CollectionPolicy
{
    /**
     * Determine if the user can view any collections.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('collections.view');
    }

    /**
     * Determine if the user can view the collection.
     */
    public function view(User $user, Collection $collection): bool
    {
        return $user->hasPermission('collections.view');
    }

    /**
     * Determine if the user can create collections.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('collections.create');
    }

    /**
     * Determine if the user can update the collection.
     * 
     * Edit window rule:
     * Collection can only be edited within the configured edit window
     */
    public function update(User $user, Collection $collection): bool
    {
        // Permission check
        if (!$user->hasPermission('collections.edit')) {
            return false;
        }

        // Cannot update cancelled collections
        if ($collection->status === 'cancelled') {
            return false;
        }

        // Check edit window
        $editDays = (int) Setting::getValue('collection_edit_window_days', 2);
        $cutoffDate = now()->subDays($editDays)->startOfDay();

        return $collection->date >= $cutoffDate;
    }

    /**
     * Determine if the user can cancel the collection.
     * Same rules as update apply
     */
    public function cancel(User $user, Collection $collection): bool
    {
        // Permission check
        if (!$user->hasPermission('collections.cancel')) {
            return false;
        }

        // Cannot cancel already cancelled collections
        if ($collection->status === 'cancelled') {
            return false;
        }

        // Check edit window (same as update)
        $editDays = (int) Setting::getValue('collection_edit_window_days', 2);
        $cutoffDate = now()->subDays($editDays)->startOfDay();

        return $collection->date >= $cutoffDate;
    }

    /**
     * Determine if the user can delete the collection.
     * Deletion is NEVER allowed - use cancellation instead (BR-COL-007)
     */
    public function delete(User $user, Collection $collection): bool
    {
        return false; // Never allow deletion
    }

    /**
     * Determine if the user can restore the collection.
     */
    public function restore(User $user, Collection $collection): bool
    {
        return false; // No restoration of cancelled collections
    }

    /**
     * Determine if the user can permanently delete the collection.
     */
    public function forceDelete(User $user, Collection $collection): bool
    {
        return false; // Never allow force delete
    }
}
