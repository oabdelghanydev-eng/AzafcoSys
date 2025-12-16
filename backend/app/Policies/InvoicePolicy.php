<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;

/**
 * Invoice Policy
 * تحديث 2025-12-14: تفعيل Permission checks
 */
class InvoicePolicy
{
    /**
     * Determine if the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view');
    }

    /**
     * Determine if the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view');
    }

    /**
     * Determine if the user can create invoices.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create');
    }

    /**
     * Determine if the user can update the invoice.
     *
     * Edit window rule (BR-INV-006):
     * Invoice can only be edited within the configured edit window
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Permission check
        if (! $user->hasPermission('invoices.edit')) {
            return false;
        }

        // Cannot update cancelled invoices
        if ($invoice->status === 'cancelled') {
            return false;
        }

        // Check edit window
        $editDays = (int) Setting::getValue('invoice_edit_window_days', 2);
        $cutoffDate = now()->subDays($editDays)->startOfDay();

        return $invoice->date >= $cutoffDate;
    }

    /**
     * Determine if the user can cancel the invoice.
     * Same rules as update apply
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        // Permission check
        if (! $user->hasPermission('invoices.cancel')) {
            return false;
        }

        // Cannot cancel already cancelled invoices
        if ($invoice->status === 'cancelled') {
            return false;
        }

        // Check edit window (same as update)
        $editDays = (int) Setting::getValue('invoice_edit_window_days', 2);
        $cutoffDate = now()->subDays($editDays)->startOfDay();

        return $invoice->date >= $cutoffDate;
    }

    /**
     * Determine if the user can delete the invoice.
     * Deletion is NEVER allowed - use cancellation instead (BR-INV-004)
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return false; // Never allow deletion
    }

    /**
     * Determine if the user can restore the invoice.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return false; // No restoration of cancelled invoices
    }

    /**
     * Determine if the user can permanently delete the invoice.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false; // Never allow force delete
    }
}
