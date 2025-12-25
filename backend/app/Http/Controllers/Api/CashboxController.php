<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAccountTransactionRequest;
use App\Services\AccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CashboxController
 *
 * Handles cashbox deposit/withdraw operations with permission checks.
 * Delegates business logic to AccountService.
 */
/**
 * @tags Cashbox
 */
class CashboxController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountService $accountService
    ) {
    }

    /**
     * Get cashbox balance and recent transactions
     * Permission: cashbox.view
     */
    public function index(): JsonResponse
    {
        $this->checkPermission('cashbox.view');

        $data = $this->accountService->getAccountWithTransactions(
            AccountService::TYPE_CASHBOX,
            limit: 20
        );

        return $this->success($data);
    }

    /**
     * Deposit to cashbox
     * Permission: cashbox.deposit
     */
    public function deposit(StoreAccountTransactionRequest $request): JsonResponse
    {
        $this->checkPermission('cashbox.deposit');

        $dto = TransactionDTO::deposit(
            amount: (float) $request->validated('amount'),
            description: $request->validated('description'),
            referenceType: $request->validated('reference_type'),
            referenceId: $request->validated('reference_id'),
        );

        $result = $this->accountService->deposit(AccountService::TYPE_CASHBOX, $dto);

        return $this->success(['new_balance' => $result['new_balance']], 'تم الإيداع بنجاح');
    }

    /**
     * Withdraw from cashbox
     * Permission: cashbox.withdraw
     */
    public function withdraw(StoreAccountTransactionRequest $request): JsonResponse
    {
        $this->checkPermission('cashbox.withdraw');

        $dto = TransactionDTO::withdraw(
            amount: (float) $request->validated('amount'),
            description: $request->validated('description'),
            referenceType: $request->validated('reference_type'),
            referenceId: $request->validated('reference_id'),
        );

        $result = $this->accountService->withdraw(AccountService::TYPE_CASHBOX, $dto);

        return $this->success(['new_balance' => $result['new_balance']], 'تم السحب بنجاح');
    }

    /**
     * Get cashbox transactions history
     * Permission: cashbox.view
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->checkPermission('cashbox.view');

        $transactions = $this->accountService->getTransactionHistory(
            AccountService::TYPE_CASHBOX,
            filters: [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'type' => $request->type,
            ],
            perPage: $request->per_page ?? 50
        );

        return $this->success($transactions);
    }
}
