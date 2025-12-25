<?php

namespace App\Services;

use App\DTOs\TransactionDTO;
use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\CashboxTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AccountService
 * 
 * Unified service for handling Cashbox and Bank operations.
 * Eliminates code duplication between CashboxController and BankController.
 * 
 * @package App\Services
 */
class AccountService extends BaseService
{
    /**
     * Account types
     */
    public const TYPE_CASHBOX = 'cashbox';
    public const TYPE_BANK = 'bank';

    /**
     * Get account with recent transactions.
     *
     * @param string $type Account type (cashbox|bank)
     * @param int $limit Number of recent transactions
     * @return array{balance: float, transactions: Collection}
     * @throws BusinessException If account not found
     */
    public function getAccountWithTransactions(string $type, int $limit = 20): array
    {
        $account = $this->getAccount($type);

        $transactions = $this->getTransactionModel($type)::query()
            ->where('account_id', $account->id)
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->take($limit)
            ->get();

        return [
            'balance' => (float) $account->balance,
            'transactions' => $transactions,
        ];
    }

    /**
     * Deposit money to account.
     *
     * @param string $type Account type (cashbox|bank)
     * @param TransactionDTO $dto Transaction data
     * @return array{new_balance: float, transaction: Model}
     * @throws BusinessException If account not found
     */
    public function deposit(string $type, TransactionDTO $dto): array
    {
        return $this->transactionWithLog("Deposit to {$type}", function () use ($type, $dto) {
            $account = $this->getAccountForUpdate($type);
            $newBalance = $account->balance + $dto->amount;

            $transaction = $this->createTransaction($type, $account, [
                'type' => 'in',
                'amount' => $dto->amount,
                'balance_after' => $newBalance,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'description' => $dto->description,
                'created_by' => auth()->id(),
            ]);

            $account->update(['balance' => $newBalance]);

            return [
                'new_balance' => (float) $newBalance,
                'transaction' => $transaction,
            ];
        }, ['type' => $type, 'amount' => $dto->amount]);
    }

    /**
     * Withdraw money from account.
     *
     * @param string $type Account type (cashbox|bank)
     * @param TransactionDTO $dto Transaction data
     * @return array{new_balance: float, transaction: Model}
     * @throws BusinessException If account not found or insufficient balance
     */
    public function withdraw(string $type, TransactionDTO $dto): array
    {
        return $this->transactionWithLog("Withdraw from {$type}", function () use ($type, $dto) {
            $account = $this->getAccountForUpdate($type);

            $this->validateSufficientBalance($account, $dto->amount, $type);

            $newBalance = $account->balance - $dto->amount;

            $transaction = $this->createTransaction($type, $account, [
                'type' => 'out',
                'amount' => $dto->amount,
                'balance_after' => $newBalance,
                'reference_type' => $dto->referenceType,
                'reference_id' => $dto->referenceId,
                'description' => $dto->description,
                'created_by' => auth()->id(),
            ]);

            $account->update(['balance' => $newBalance]);

            return [
                'new_balance' => (float) $newBalance,
                'transaction' => $transaction,
            ];
        }, ['type' => $type, 'amount' => $dto->amount]);
    }

    /**
     * Get paginated transaction history.
     *
     * @param string $type Account type (cashbox|bank)
     * @param array $filters Optional filters (date_from, date_to, type)
     * @param int $perPage Items per page
     * @return LengthAwarePaginator
     * @throws BusinessException If account not found
     */
    public function getTransactionHistory(
        string $type,
        array $filters = [],
        int $perPage = 50
    ): LengthAwarePaginator {
        $account = $this->getAccount($type);

        return $this->getTransactionModel($type)::query()
            ->where('account_id', $account->id)
            ->with('createdBy:id,name')
            ->when($filters['date_from'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['date_to'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get account balance.
     *
     * @param string $type Account type (cashbox|bank)
     * @return float
     * @throws BusinessException If account not found
     */
    public function getBalance(string $type): float
    {
        return (float) $this->getAccount($type)->balance;
    }

    /**
     * Get active account by type.
     *
     * @param string $type Account type (cashbox|bank)
     * @return Account
     * @throws BusinessException If account not found
     */
    protected function getAccount(string $type): Account
    {
        $account = $this->getAccountQuery($type)->first();

        if (!$account) {
            $this->throwAccountNotFoundError($type);
        }

        return $account;
    }

    /**
     * Get account with lock for update.
     *
     * @param string $type Account type (cashbox|bank)
     * @return Account
     * @throws BusinessException If account not found
     */
    protected function getAccountForUpdate(string $type): Account
    {
        $account = $this->getAccountQuery($type)->lockForUpdate()->first();

        if (!$account) {
            $this->throwAccountNotFoundError($type);
        }

        return $account;
    }

    /**
     * Get the base account query for type.
     */
    protected function getAccountQuery(string $type)
    {
        return match ($type) {
            self::TYPE_CASHBOX => Account::cashbox()->active(),
            self::TYPE_BANK => Account::bank()->active(),
            default => throw new \InvalidArgumentException("Invalid account type: {$type}"),
        };
    }

    /**
     * Get the transaction model class for type.
     *
     * @param string $type Account type
     * @return string Model class name
     */
    protected function getTransactionModel(string $type): string
    {
        return match ($type) {
            self::TYPE_CASHBOX => CashboxTransaction::class,
            self::TYPE_BANK => BankTransaction::class,
            default => throw new \InvalidArgumentException("Invalid account type: {$type}"),
        };
    }

    /**
     * Create a transaction record.
     */
    protected function createTransaction(string $type, Account $account, array $data): Model
    {
        $modelClass = $this->getTransactionModel($type);

        return $modelClass::create([
            'account_id' => $account->id,
            ...$data,
        ]);
    }

    /**
     * Validate account has sufficient balance for withdrawal.
     *
     * @throws BusinessException If insufficient balance
     */
    protected function validateSufficientBalance(Account $account, float $amount, string $type): void
    {
        if ($account->balance < $amount) {
            $this->throwBusinessError(
                'FIN_002',
                'الرصيد غير كافي',
                'Insufficient balance'
            );
        }
    }

    /**
     * Throw account not found error based on type.
     *
     * @throws BusinessException
     */
    protected function throwAccountNotFoundError(string $type): never
    {
        match ($type) {
            self::TYPE_CASHBOX => $this->throwBusinessError(
                'FIN_001',
                'الخزنة غير موجودة',
                'Cashbox not found'
            ),
            self::TYPE_BANK => $this->throwBusinessError(
                'FIN_003',
                'الحساب البنكي غير موجود',
                'Bank account not found'
            ),
            default => $this->throwBusinessError(
                'FIN_000',
                'الحساب غير موجود',
                'Account not found'
            ),
        };
    }

    protected function getServiceName(): string
    {
        return 'AccountService';
    }
}
