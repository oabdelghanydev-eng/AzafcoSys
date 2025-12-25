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
 * BankController
 *
 * Handles bank deposit/withdraw operations with permission checks.
 * Delegates business logic to AccountService.
 */
/**
 * @tags Bank
 */
class BankController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AccountService $accountService
    ) {
    }

    /**
     * Get bank balance and recent transactions
     * Permission: bank.view
     */
    public function index(): JsonResponse
    {
        $this->checkPermission('bank.view');

        $data = $this->accountService->getAccountWithTransactions(
            AccountService::TYPE_BANK,
            limit: 20
        );

        return $this->success($data);
    }

    /**
     * Deposit to bank
     * Permission: bank.deposit
     */
    public function deposit(StoreAccountTransactionRequest $request): JsonResponse
    {
        $this->checkPermission('bank.deposit');

        $dto = TransactionDTO::deposit(
            amount: (float) $request->validated('amount'),
            description: $request->validated('description'),
            referenceType: $request->validated('reference_type'),
            referenceId: $request->validated('reference_id'),
        );

        $result = $this->accountService->deposit(AccountService::TYPE_BANK, $dto);

        return $this->success(['new_balance' => $result['new_balance']], 'تم الإيداع بنجاح');
    }

    /**
     * Withdraw from bank
     * Permission: bank.withdraw
     */
    public function withdraw(StoreAccountTransactionRequest $request): JsonResponse
    {
        $this->checkPermission('bank.withdraw');

        $dto = TransactionDTO::withdraw(
            amount: (float) $request->validated('amount'),
            description: $request->validated('description'),
            referenceType: $request->validated('reference_type'),
            referenceId: $request->validated('reference_id'),
        );

        $result = $this->accountService->withdraw(AccountService::TYPE_BANK, $dto);

        return $this->success(['new_balance' => $result['new_balance']], 'تم السحب بنجاح');
    }

    /**
     * Get bank transactions history
     * Permission: bank.view
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->checkPermission('bank.view');

        $transactions = $this->accountService->getTransactionHistory(
            AccountService::TYPE_BANK,
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
