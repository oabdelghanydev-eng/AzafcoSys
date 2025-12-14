<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

/**
 * Shipment Policy
 * Created 2025-12-14: Permission checks for shipment operations
 */
class ShipmentPolicy
{
    /**
     * Determine if the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shipments.view');
    }

    /**
     * Determine if the user can view the shipment.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shipments.view');
    }

    /**
     * Determine if the user can create shipments.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('shipments.create');
    }

    /**
     * Determine if the user can update the shipment.
     */
    public function update(User $user, Shipment $shipment): bool
    {
        // Permission check
        if (!$user->hasPermission('shipments.edit')) {
            return false;
        }

        // Cannot update settled shipments
        if ($shipment->status === 'settled') {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can delete the shipment.
     */
    public function delete(User $user, Shipment $shipment): bool
    {
        // Permission check
        if (!$user->hasPermission('shipments.delete')) {
            return false;
        }

        // Cannot delete settled shipments
        if ($shipment->status === 'settled') {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can close the shipment.
     */
    public function close(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shipments.close');
    }

    /**
     * Determine if the user can settle the shipment.
     */
    public function settle(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shipments.close');
    }

    /**
     * Determine if the user can unsettle the shipment.
     */
    public function unsettle(User $user, Shipment $shipment): bool
    {
        return $user->hasPermission('shipments.close');
    }
}
