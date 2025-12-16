<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\CashboxTransaction;
use App\Models\Expense;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

class ExpenseObserver
{
    /**
     * Handle the Expense "created" event.
     * 1. If supplier expense, increase supplier balance (we owe them less)
     * 2. Decrease account balance
     * 3. Create transaction record
     */
    public function created(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            // If supplier expense, update supplier balance
            if ($expense->type === 'supplier' && $expense->supplier_id) {
                Supplier::where('id', $expense->supplier_id)
                    ->decrement('balance', (float) $expense->amount);
            }

            // Get account based on payment method with lock (EC-TRS-001)
            $account = Account::where('type', $expense->payment_method === 'cash' ? 'cashbox' : 'bank')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($account) {
                // EC-TRS-001: Check sufficient balance (only for cashbox)
                if ($expense->payment_method === 'cash' && $account->balance < $expense->amount) {
                    throw new \App\Exceptions\BusinessException(
                        'TRS_001',
                        'رصيد الخزنة غير كافي. المتاح: '.$account->balance,
                        'Insufficient cashbox balance. Available: '.$account->balance
                    );
                }

                // Decrease account balance
                $account->decrement('balance', (float) $expense->amount);

                // Create transaction record
                $transactionData = [
                    'account_id' => $account->id,
                    'type' => 'out',
                    'amount' => $expense->amount,
                    'balance_after' => $account->balance,
                    'reference_type' => Expense::class,
                    'reference_id' => $expense->id,
                    'description' => $expense->description,
                    'created_by' => auth()->id(),
                ];

                if ($expense->payment_method === 'cash') {
                    CashboxTransaction::create($transactionData);
                } else {
                    BankTransaction::create($transactionData);
                }
            }
        });

        AuditService::logCreate($expense);
    }

    /**
     * Handle the Expense "deleted" event.
     * Reverse all changes
     */
    public function deleted(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            // If supplier expense, decrease supplier balance
            if ($expense->type === 'supplier' && $expense->supplier_id) {
                Supplier::where('id', $expense->supplier_id)
                    ->increment('balance', (float) $expense->amount);
            }

            // Get account and increase balance
            $account = Account::where('type', $expense->payment_method === 'cash' ? 'cashbox' : 'bank')
                ->where('is_active', true)
                ->first();

            if ($account) {
                $account->increment('balance', (float) $expense->amount);

                // Delete related transaction
                if ($expense->payment_method === 'cash') {
                    CashboxTransaction::where('reference_type', Expense::class)
                        ->where('reference_id', $expense->id)
                        ->delete();
                } else {
                    BankTransaction::where('reference_type', Expense::class)
                        ->where('reference_id', $expense->id)
                        ->delete();
                }
            }
        });

        AuditService::logDelete($expense);
    }
}
