<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Expense;
use App\Models\Account;
use App\Models\User;
use App\Models\Supplier;

/**
 * Feature Tests for Expense Endpoints
 * Epic 7: Treasury & Cash Management
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Account $cashbox;
    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'permissions' => ['expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete'],
        ]);

        $this->supplier = Supplier::factory()->create([
            'balance' => 5000,
        ]);

        // Create accounts
        $this->cashbox = Account::create([
            'name' => 'الخزنة الرئيسية',
            'type' => 'cashbox',
            'balance' => 10000,
            'is_active' => true,
        ]);

        $this->bank = Account::create([
            'name' => 'البنك الأهلي',
            'type' => 'bank',
            'balance' => 50000,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to bypass EnsureWorkingDay middleware
     */
    private function expenseRequest(string $method, string $uri, array $data = [])
    {
        return $this->actingAs($this->user)
                    ->withoutMiddleware(\App\Http\Middleware\EnsureWorkingDay::class)
            ->{$method}($uri, $data);
    }

    // ============================================
    // Expense Creation Tests
    // ============================================

    public function test_can_create_company_expense(): void
    {
        $response = $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'company',
            'date' => now()->toDateString(),
            'amount' => 500,
            'description' => 'مصاريف مكتبية',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'type' => 'company',
            'amount' => 500,
        ]);
    }

    public function test_can_create_supplier_expense(): void
    {
        $response = $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'description' => 'دفعة للمورد',
            'payment_method' => 'bank',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
        ]);
    }

    public function test_supplier_expense_requires_supplier_id(): void
    {
        $response = $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'supplier',
            'date' => now()->toDateString(),
            'amount' => 1000,
            'description' => 'دفعة للمورد',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        // The required_if validation kicks in first
        $response->assertJsonValidationErrors('supplier_id');
    }

    // ============================================
    // Account Balance Tests
    // ============================================

    public function test_cash_expense_decreases_cashbox_balance(): void
    {
        $initialBalance = $this->cashbox->balance;

        $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'company',
            'date' => now()->toDateString(),
            'amount' => 500,
            'description' => 'مصاريف',
            'payment_method' => 'cash',
        ]);

        $this->cashbox->refresh();
        $this->assertEqualsWithDelta(
            (float) $initialBalance - 500,
            (float) $this->cashbox->balance,
            0.01
        );
    }

    public function test_bank_expense_decreases_bank_balance(): void
    {
        $initialBalance = $this->bank->balance;

        $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'company',
            'date' => now()->toDateString(),
            'amount' => 2000,
            'description' => 'تحويل بنكي',
            'payment_method' => 'bank',
        ]);

        $this->bank->refresh();
        $this->assertEqualsWithDelta(
            (float) $initialBalance - 2000,
            (float) $this->bank->balance,
            0.01
        );
    }

    public function test_supplier_expense_decreases_supplier_balance(): void
    {
        $initialBalance = $this->supplier->balance;

        $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'date' => now()->toDateString(),
            'amount' => 1000,
            'description' => 'دفعة',
            'payment_method' => 'cash',
        ]);

        $this->supplier->refresh();
        $this->assertEqualsWithDelta(
            (float) $initialBalance - 1000,
            (float) $this->supplier->balance,
            0.01
        );
    }

    // ============================================
    // Validation Tests
    // ============================================

    public function test_expense_requires_amount(): void
    {
        $response = $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'company',
            'date' => now()->toDateString(),
            'description' => 'مصاريف',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_expense_requires_description(): void
    {
        $response = $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'company',
            'date' => now()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('description');
    }

    // ============================================
    // List and Filter Tests
    // ============================================

    public function test_can_list_expenses(): void
    {
        Expense::factory()->count(3)->create([
            'created_by' => $this->user->id,
        ]);

        $response = $this->expenseRequest('getJson', '/api/expenses');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    // ============================================
    // Overdraft Prevention Tests (EC-TRS-001)
    // ============================================

    public function test_cannot_overdraw_cashbox(): void
    {
        // Cashbox has 10000, try to spend 20000
        $response = $this->expenseRequest('postJson', '/api/expenses', [
            'type' => 'company',
            'date' => now()->toDateString(),
            'amount' => 20000,
            'description' => 'مبلغ كبير',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'TRS_001');
    }
}
