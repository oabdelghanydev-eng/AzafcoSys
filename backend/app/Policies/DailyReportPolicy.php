<?php

namespace App\Policies;

use App\Models\User;

/**
 * DailyReportPolicy
 *
 * Authorization for daily report operations
 */
class DailyReportPolicy
{
    /**
     * Determine if user can close daily report
     */
    public function close(User $user): bool
    {
        return $user->hasPermission('daily.close');
    }

    /**
     * Determine if user can reopen daily report
     */
    public function reopen(User $user): bool
    {
        return $user->hasPermission('daily.reopen');
    }
}
