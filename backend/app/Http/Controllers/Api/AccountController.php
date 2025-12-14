<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashboxTransaction;
use App\Models\BankTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
            ],
            'bank' => [
                'account' => $bank,
                'balance' => $bank ? (float) $bank->balance : 0,
            ],
            'total' => (float) Account::active()->sum('balance'),
        ]);
    }
}
