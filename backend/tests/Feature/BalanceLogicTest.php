<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer/Supplier Balance Tests
 * تحسين 2025-12-16: اختبارات أرصدة العملاء والموردين
 */
class BalanceLogicTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);
    }

    // ============================================
    // Customer Balance Logic Tests
    // ============================================

    public function test_customer_starts_with_zero_balance(): void
    {
        $customer = Customer::factory()->create();

        $this->assertEquals(0, $customer->balance);
    }

    public function test_customer_balance_positive_means_debtor(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 1000.00,
        ]);

        $this->assertGreaterThan(0, $customer->balance);
        $this->assertStringContainsString('مديون', $customer->formatted_balance);
    }

    public function test_customer_balance_negative_means_creditor(): void
    {
        $customer = Customer::factory()->create([
            'balance' => -500.00,
        ]);

        $this->assertLessThan(0, $customer->balance);
        $this->assertStringContainsString('دائن', $customer->formatted_balance);
    }

    public function test_customer_scope_with_debt_filters_correctly(): void
    {
        $debtor = Customer::factory()->create(['balance' => 100]);
        $creditor = Customer::factory()->create(['balance' => -100]);
        $zero = Customer::factory()->create(['balance' => 0]);

        $debtors = Customer::withDebt()->get();

        $this->assertCount(1, $debtors);
        $this->assertEquals($debtor->id, $debtors->first()->id);
    }

    // ============================================
    // Supplier Balance Logic Tests
    // ============================================

    public function test_supplier_starts_with_zero_balance(): void
    {
        $supplier = Supplier::factory()->create();

        $this->assertEquals(0, $supplier->balance);
    }

    public function test_supplier_balance_positive_means_we_owe_them(): void
    {
        $supplier = Supplier::factory()->create([
            'balance' => 5000.00,
        ]);

        $this->assertGreaterThan(0, $supplier->balance);
        $this->assertStringContainsString('له', $supplier->formatted_balance);
    }

    public function test_supplier_balance_negative_means_they_owe_us(): void
    {
        $supplier = Supplier::factory()->create([
            'balance' => -1000.00,
        ]);

        $this->assertLessThan(0, $supplier->balance);
        $this->assertStringContainsString('عليه', $supplier->formatted_balance);
    }

    // ============================================
    // Customer API Tests
    // ============================================

    public function test_customer_api_returns_balance(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 1500.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);

        // Balance may be returned as string or number
        $this->assertEquals(1500, (float) $response->json('data.balance'));
    }

    public function test_cannot_delete_customer_with_balance(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 100.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'CUS_003');
    }

    public function test_can_delete_customer_with_zero_balance_and_no_records(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    // ============================================
    // Supplier API Tests
    // ============================================

    public function test_supplier_api_returns_balance(): void
    {
        $supplier = Supplier::factory()->create([
            'balance' => 3000.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200);

        // Balance may be returned as string or number
        $this->assertEquals(3000, (float) $response->json('data.balance'));
    }

    public function test_cannot_delete_supplier_with_balance(): void
    {
        $supplier = Supplier::factory()->create([
            'balance' => 500.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SUP_003');
    }

    // ============================================
    // Balance Formatting Tests
    // ============================================

    public function test_customer_formatted_balance_includes_currency(): void
    {
        $customer = Customer::factory()->create([
            'balance' => 1234.56,
        ]);

        $formatted = $customer->formatted_balance;

        $this->assertStringContainsString('1,234.56', $formatted);
        $this->assertStringContainsString('ج.م', $formatted);
    }

    public function test_supplier_formatted_balance_includes_currency(): void
    {
        $supplier = Supplier::factory()->create([
            'balance' => 9876.54,
        ]);

        $formatted = $supplier->formatted_balance;

        $this->assertStringContainsString('9,876.54', $formatted);
        $this->assertStringContainsString('ج.م', $formatted);
    }
}
