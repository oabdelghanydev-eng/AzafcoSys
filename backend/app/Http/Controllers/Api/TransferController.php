<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Account;
use App\Models\CashboxTransaction;
use App\Models\BankTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @tags Transfer
 */
class TransferController extends Controller
{
    use ApiResponse;

    /**
     * List transfers
     */
    public function index(Request $request): JsonResponse
    {
        $transfers = Transfer::with(['fromAccount:id,name,type', 'toAccount:id,name,type', 'createdBy:id,name'])
            ->when($request->date_from, fn($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->per_page ?? 20);

        return $this->success($transfers);
    }

    /**
     * Create transfer between accounts
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required|exists:accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $fromAccount = Account::findOrFail($validated['from_account_id']);
        $toAccount = Account::findOrFail($validated['to_account_id']);

        // Check sufficient balance
        if ($fromAccount->balance < $validated['amount']) {
            return $this->error(
                'TRF_001',
                'رصيد الحساب المصدر غير كافي',
                'Insufficient balance in source account',
                422
            );
        }

        return DB::transaction(function () use ($validated, $fromAccount, $toAccount) {
            // Create transfer record
            $transfer = Transfer::create([
                'from_account_id' => $validated['from_account_id'],
                'to_account_id' => $validated['to_account_id'],
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // Deduct from source account
            $fromAccount->decrement('balance', $validated['amount']);

            // Create transaction record for source account
            $this->createTransaction(
                $fromAccount,
                'out',
                $validated['amount'],
                'Transfer',
                $transfer->id,
                "تحويل إلى {$toAccount->name}"
            );

            // Add to destination account
            $toAccount->increment('balance', $validated['amount']);

            // Create transaction record for destination account
            $this->createTransaction(
                $toAccount,
                'in',
                $validated['amount'],
                'Transfer',
                $transfer->id,
                "تحويل من {$fromAccount->name}"
            );

            return $this->success(
                $transfer->load(['fromAccount', 'toAccount']),
                'تم التحويل بنجاح',
                201
            );
        });
    }

    /**
     * Show single transfer
     */
    public function show(Transfer $transfer): JsonResponse
    {
        return $this->success(
            $transfer->load(['fromAccount', 'toAccount', 'createdBy'])
        );
    }

    /**
     * Create transaction record for account
     */
    private function createTransaction(
        Account $account,
        string $type,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description
    ): void {
        $data = [
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $account->fresh()->balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => auth()->id(),
        ];

        if ($account->type === 'cashbox') {
            CashboxTransaction::create($data);
        } else {
            BankTransaction::create($data);
        }
    }
}
