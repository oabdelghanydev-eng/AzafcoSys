<?php

namespace App\Observers;

use App\Models\ReturnModel;
use App\Services\AuditService;

class ReturnObserver
{
    /**
     * Handle the Return "created" event.
     * Note: Most logic is in ReturnService
     * This observer handles any post-creation tasks
     */
    public function created(ReturnModel $return): void
    {
        AuditService::logCreate($return);
    }

    /**
     * Handle the Return "updated" event.
     * 
     * NOTE: Cancellation logic is handled EXCLUSIVELY by ReturnService::cancelReturn()
     * to prevent double-execution of ledger reversals.
     * DO NOT add cancellation logic here.
     */
    public function updated(ReturnModel $return): void
    {
        AuditService::logUpdate($return, $return->getOriginal());
    }
}
