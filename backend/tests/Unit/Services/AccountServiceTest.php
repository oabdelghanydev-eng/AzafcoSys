<?php

namespace Tests\Unit\Services;

use App\DTOs\TransactionDTO;
use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\BankTransaction;
use App\Models\CashboxTransaction;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AccountService Unit Tests
 * 
 * Tests for unified Cashbox and Bank operations.
 */
class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AccountService::class);
    }

    // ==========================================
    // CASHBOX TESTS
    // ==========================================

    /** @test */
    public function it_can_deposit_to_cashbox(): void
    {
        $cashbox = Account::factory()->create([
            'type' => 'cashbox',
            'balance' => 1000,
            'is_active' => true,
        ]);

        $dto = TransactionDTO::deposit(500, 'Test deposit');

        $result = $this->service->deposit(AccountService::TYPE_CASHBOX, $dto);

        $this->assertEquals(1500, $result['new_balance']);
        $this->assertEquals(1500, $cashbox->fresh()->balance);
        $this->assertDatabaseHas('cashbox_transactions', [
            'account_id' => $cashbox->id,
            'type' => 'in',
            'amount' => 500,
            'balance_after' => 1500,
        ]);
    }

    /** @test */
    public function it_can_withdraw_from_cashbox(): void
    {
        $cashbox = Account::factory()->create([
            'type' => 'cashbox',
            'balance' => 1000,
            'is_active' => true,
        ]);

        $dto = TransactionDTO::withdraw(300, 'Test withdrawal');

        $result = $this->service->withdraw(AccountService::TYPE_CASHBOX, $dto);

        $this->assertEquals(700, $result['new_balance']);
        $this->assertEquals(700, $cashbox->fresh()->balance);
    }

    /** @test */
    public function it_throws_exception_on_insufficient_cashbox_balance(): void
    {
        Account::factory()->create([
            'type' => 'cashbox',
            'balance' => 100,
            'is_active' => true,
        ]);

        $dto = TransactionDTO::withdraw(500, 'Too much');

        $this->expectException(BusinessException::class);

        $this->service->withdraw(AccountService::TYPE_CASHBOX, $dto);
    }

    /** @test */
    public function it_throws_exception_when_cashbox_not_found(): void
    {
        // No cashbox account exists
        $dto = TransactionDTO::deposit(100, 'Test');

        $this->expectException(BusinessException::class);

        $this->service->deposit(AccountService::TYPE_CASHBOX, $dto);
    }

    // ==========================================
    // BANK TESTS
    // ==========================================

    /** @test */
    public function it_can_deposit_to_bank(): void
    {
        $bank = Account::factory()->create([
            'type' => 'bank',
            'balance' => 5000,
            'is_active' => true,
        ]);

        $dto = TransactionDTO::deposit(2000, 'Bank deposit');

        $result = $this->service->deposit(AccountService::TYPE_BANK, $dto);

        $this->assertEquals(7000, $result['new_balance']);
        $this->assertEquals(7000, $bank->fresh()->balance);
        $this->assertDatabaseHas('bank_transactions', [
            'account_id' => $bank->id,
            'type' => 'in',
            'amount' => 2000,
        ]);
    }

    /** @test */
    public function it_can_withdraw_from_bank(): void
    {
        $bank = Account::factory()->create([
            'type' => 'bank',
            'balance' => 5000,
            'is_active' => true,
        ]);

        $dto = TransactionDTO::withdraw(1500, 'Bank withdrawal');

        $result = $this->service->withdraw(AccountService::TYPE_BANK, $dto);

        $this->assertEquals(3500, $result['new_balance']);
    }

    /** @test */
    public function it_throws_exception_on_insufficient_bank_balance(): void
    {
        Account::factory()->create([
            'type' => 'bank',
            'balance' => 1000,
            'is_active' => true,
        ]);

        $dto = TransactionDTO::withdraw(5000, 'Too much');

        $this->expectException(BusinessException::class);

        $this->service->withdraw(AccountService::TYPE_BANK, $dto);
    }

    // ==========================================
    // TRANSACTION HISTORY TESTS
    // ==========================================

    /** @test */
    public function it_can_get_transaction_history_with_filters(): void
    {
        $cashbox = Account::factory()->create([
            'type' => 'cashbox',
            'balance' => 1000,
            'is_active' => true,
        ]);

        CashboxTransaction::factory()->count(5)->create([
            'account_id' => $cashbox->id,
            'type' => 'in',
        ]);

        CashboxTransaction::factory()->count(3)->create([
            'account_id' => $cashbox->id,
            'type' => 'out',
        ]);

        // Get all
        $all = $this->service->getTransactionHistory(AccountService::TYPE_CASHBOX);
        $this->assertEquals(8, $all->total());

        // Filter by type
        $deposits = $this->service->getTransactionHistory(
            AccountService::TYPE_CASHBOX,
            ['type' => 'in']
        );
        $this->assertEquals(5, $deposits->total());
    }

    /** @test */
    public function it_can_get_account_balance(): void
    {
        Account::factory()->create([
            'type' => 'cashbox',
            'balance' => 1234.56,
            'is_active' => true,
        ]);

        $balance = $this->service->getBalance(AccountService::TYPE_CASHBOX);

        $this->assertEquals(1234.56, $balance);
    }

    // ==========================================
    // DTO TESTS
    // ==========================================

    /** @test */
    public function transaction_dto_factory_methods_work(): void
    {
        $deposit = TransactionDTO::deposit(100, 'Deposit desc');
        $this->assertEquals('deposit', $deposit->type);
        $this->assertTrue($deposit->isDeposit());
        $this->assertFalse($deposit->isWithdraw());

        $withdraw = TransactionDTO::withdraw(200, 'Withdraw desc');
        $this->assertEquals('withdraw', $withdraw->type);
        $this->assertTrue($withdraw->isWithdraw());
        $this->assertFalse($withdraw->isDeposit());
    }
}
