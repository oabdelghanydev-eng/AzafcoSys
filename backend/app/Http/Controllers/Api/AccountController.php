<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Account
 */
class AccountController extends Controller
{
    use ApiResponse;

    /**
     * List all accounts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Account::query()
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->has('active'), fn($q) => $q->where('is_active', $request->active))
            ->orderBy('type')
            ->orderBy('name');

        $accounts = $query->get();

        return $this->success($accounts);
    }

    /**
     * Show single account with recent transactions
     */
    public function show(Account $account): JsonResponse
    {
        $recentTransactions = [];

        if ($account->type === 'cashbox') {
            $recentTransactions = $account->cashboxTransactions()
                ->orderByDesc('created_at')
                ->take(20)
                ->get();
        } else {
            $recentTransactions = $account->bankTransactions()
                ->orderByDesc('created_at')
                ->take(20)
                ->get();
        }

        return $this->success([
            'account' => $account,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    /**
     * Get account transactions
     */
    public function transactions(Request $request, Account $account): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'type' => 'nullable|in:in,out',
        ]);

        if ($account->type === 'cashbox') {
            $query = $account->cashboxTransactions();
        } else {
            $query = $account->bankTransactions();
        }

        $transactions = $query
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return $this->success($transactions);
    }

    /**
     * Get account summary (balances for all accounts)
     */
    public function summary(): JsonResponse
    {
        $cashbox = Account::cashbox()->active()->first();
        $bank = Account::bank()->active()->first();

        return $this->success([
            'cashbox' => [
                'account' => $cashbox,
                'balance' => $cashbox ? (float) $cashbox->balance : 0,
                'opening_balance' => $cashbox ? (float) $cashbox->opening_balance : 0,
            ],
            'bank' => [
                'account' => $bank,
                'balance' => $bank ? (float) $bank->balance : 0,
                'opening_balance' => $bank ? (float) $bank->opening_balance : 0,
            ],
            'total' => (float) Account::active()->sum('balance'),
        ]);
    }

    /**
     * Set opening balance for an account.
     * 
     * This can only be set ONCE (or when balance equals current opening_balance).
     * Used for initial system setup to enter existing balances.
     * 
     * Permission: accounts.set_opening_balance
     */
    public function setOpeningBalance(Request $request, Account $account): JsonResponse
    {
        $this->checkPermission('accounts.set_opening_balance');

        $request->validate([
            'opening_balance' => 'required|numeric|min:0',
        ]);

        // Safety check: Can only set if:
        // 1. Opening balance was never set (opening_balance_set_at is null), OR
        // 2. Balance equals current opening_balance (no transactions yet)
        if ($account->opening_balance_set_at !== null) {
            // Check if any transactions have been made
            $hasTransactions = $account->balance != $account->opening_balance;

            if ($hasTransactions) {
                return $this->error(
                    'ACC_001',
                    'لا يمكن تغيير الرصيد الافتتاحي بعد وجود حركات',
                    'Cannot change opening balance after transactions exist',
                    422
                );
            }
        }

        $newOpeningBalance = (float) $request->opening_balance;

        // Calculate the difference to adjust current balance
        $difference = $newOpeningBalance - (float) $account->opening_balance;

        $account->update([
            'opening_balance' => $newOpeningBalance,
            'balance' => (float) $account->balance + $difference,
            'opening_balance_set_at' => now(),
            'opening_balance_set_by' => auth()->id(),
        ]);

        // Log for audit
        \App\Services\AuditService::logUpdate($account, [
            'opening_balance' => $account->getOriginal('opening_balance'),
        ]);

        return $this->success($account->fresh(), 'تم تعيين الرصيد الافتتاحي بنجاح');
    }
}
