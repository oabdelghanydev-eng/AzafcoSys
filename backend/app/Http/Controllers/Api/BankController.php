<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankTransaction;
use App\Traits\ApiResponse;
use App\Exceptions\BusinessException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * BankController
 * 
 * Handles bank deposit/withdraw operations with permission checks
 */
/**
 * @tags Bank
 */
class BankController extends Controller
{
    use ApiResponse;

    /**
     * Get bank balance and recent transactions
     * Permission: bank.view
     */
    public function index(): JsonResponse
    {
        $this->checkPermission('bank.view');

        $bank = Account::bank()->active()->first();

        if (!$bank) {
            return $this->error('FIN_003', 'الحساب البنكي غير موجود', 'Bank account not found', 404);
        }

        $transactions = $bank->bankTransactions()
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        return $this->success([
            'balance' => (float) $bank->balance,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Deposit to bank
     * Permission: bank.deposit
     */
    public function deposit(Request $request): JsonResponse
    {
        $this->checkPermission('bank.deposit');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $bank = Account::bank()->active()->lockForUpdate()->first();

            if (!$bank) {
                throw new BusinessException('FIN_003', 'الحساب البنكي غير موجود', 'Bank account not found');
            }

            $newBalance = $bank->balance + $validated['amount'];

            BankTransaction::create([
                'account_id' => $bank->id,
                'type' => 'in',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $newBalance]);

            return $this->success([
                'new_balance' => (float) $newBalance,
            ], 'تم الإيداع بنجاح');
        });
    }

    /**
     * Withdraw from bank
     * Permission: bank.withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $this->checkPermission('bank.withdraw');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $bank = Account::bank()->active()->lockForUpdate()->first();

            if (!$bank) {
                throw new BusinessException('FIN_003', 'الحساب البنكي غير موجود', 'Bank account not found');
            }

            if ($bank->balance < $validated['amount']) {
                throw new BusinessException('FIN_002', 'الرصيد غير كافي', 'Insufficient balance');
            }

            $newBalance = $bank->balance - $validated['amount'];

            BankTransaction::create([
                'account_id' => $bank->id,
                'type' => 'out',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);

            $bank->update(['balance' => $newBalance]);

            return $this->success([
                'new_balance' => (float) $newBalance,
            ], 'تم السحب بنجاح');
        });
    }

    /**
     * Get bank transactions history
     * Permission: bank.view
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->checkPermission('bank.view');

        $bank = Account::bank()->active()->first();

        if (!$bank) {
            return $this->error('FIN_003', 'الحساب البنكي غير موجود', 'Bank account not found', 404);
        }

        $transactions = $bank->bankTransactions()
            ->with('createdBy:id,name')
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return $this->success($transactions);
    }
}
