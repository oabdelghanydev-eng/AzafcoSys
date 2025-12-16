<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashboxTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CashboxController
 *
 * Handles cashbox deposit/withdraw operations with permission checks
 */
/**
 * @tags Cashbox
 */
class CashboxController extends Controller
{
    use ApiResponse;

    /**
     * Get cashbox balance and recent transactions
     * Permission: cashbox.view
     */
    public function index(): JsonResponse
    {
        $this->checkPermission('cashbox.view');

        $cashbox = Account::cashbox()->active()->first();

        if (! $cashbox) {
            return $this->error('FIN_001', 'الخزنة غير موجودة', 'Cashbox not found', 404);
        }

        $transactions = $cashbox->cashboxTransactions()
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        return $this->success([
            'balance' => (float) $cashbox->balance,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Deposit to cashbox
     * Permission: cashbox.deposit
     */
    public function deposit(Request $request): JsonResponse
    {
        $this->checkPermission('cashbox.deposit');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $cashbox = Account::cashbox()->active()->lockForUpdate()->first();

            if (! $cashbox) {
                throw new BusinessException('FIN_001', 'الخزنة غير موجودة', 'Cashbox not found');
            }

            $newBalance = $cashbox->balance + $validated['amount'];

            CashboxTransaction::create([
                'account_id' => $cashbox->id,
                'type' => 'in',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);

            $cashbox->update(['balance' => $newBalance]);

            return $this->success([
                'new_balance' => (float) $newBalance,
            ], 'تم الإيداع بنجاح');
        });
    }

    /**
     * Withdraw from cashbox
     * Permission: cashbox.withdraw
     */
    public function withdraw(Request $request): JsonResponse
    {
        $this->checkPermission('cashbox.withdraw');

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        return DB::transaction(function () use ($validated) {
            $cashbox = Account::cashbox()->active()->lockForUpdate()->first();

            if (! $cashbox) {
                throw new BusinessException('FIN_001', 'الخزنة غير موجودة', 'Cashbox not found');
            }

            if ($cashbox->balance < $validated['amount']) {
                throw new BusinessException('FIN_002', 'الرصيد غير كافي', 'Insufficient balance');
            }

            $newBalance = $cashbox->balance - $validated['amount'];

            CashboxTransaction::create([
                'account_id' => $cashbox->id,
                'type' => 'out',
                'amount' => $validated['amount'],
                'balance_after' => $newBalance,
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'description' => $validated['description'],
                'created_by' => auth()->id(),
            ]);

            $cashbox->update(['balance' => $newBalance]);

            return $this->success([
                'new_balance' => (float) $newBalance,
            ], 'تم السحب بنجاح');
        });
    }

    /**
     * Get cashbox transactions history
     * Permission: cashbox.view
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->checkPermission('cashbox.view');

        $cashbox = Account::cashbox()->active()->first();

        if (! $cashbox) {
            return $this->error('FIN_001', 'الخزنة غير موجودة', 'Cashbox not found', 404);
        }

        $transactions = $cashbox->cashboxTransactions()
            ->with('createdBy:id,name')
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 50);

        return $this->success($transactions);
    }
}
