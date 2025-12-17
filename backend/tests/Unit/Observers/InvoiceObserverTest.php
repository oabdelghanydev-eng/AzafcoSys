<?php

namespace Tests\Unit\Observers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Collection;
use App\Models\CollectionAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InvoiceObserver Unit Tests
 * 
 * Tests critical business rules:
 * - BR-INV-001: Customer balance update on creation
 * - BR-INV-002: Invoice balance calculation
 * - BR-INV-003: Cancellation logic (allocation reversal)
 * - BR-INV-004: Deletion prevention
 * - BR-INV-005: Total reduction validation
 */
class InvoiceObserverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * BR-INV-001: Invoice creation increases customer balance
     */
    public function it_increases_customer_balance_when_invoice_created(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 100]);

        // Act
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'status' => 'active',
        ]);

        // Assert
        $this->assertEquals(600, $customer->fresh()->balance);
        $this->assertEquals(500, $invoice->fresh()->balance);
    }

    /**
     * @test
     * BR-INV-002: Invoice balance is calculated correctly
     */
    public function it_sets_initial_balance_equal_to_total(): void
    {
        // Act
        $invoice = Invoice::factory()->create([
            'total' => 750,
            'paid_amount' => 0,
        ]);

        // Assert
        $this->assertEquals(750, $invoice->balance);
    }

    /**
     * @test
     * BR-INV-003: Cancelling invoice reverses allocations and adjusts balances
     */
    public function it_reverses_allocations_when_invoice_cancelled(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 0]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 1000,
            'paid_amount' => 600,
            'balance' => 400,
            'status' => 'active',
        ]);

        // Simulate allocations
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 600,
        ]);

        CollectionAllocation::create([
            'collection_id' => $collection->id,
            'invoice_id' => $invoice->id,
            'amount' => 600,
        ]);

        // Customer balance after invoice and payment
        $customer->update(['balance' => 1000 - 600]); // 400

        // Act - Cancel the invoice
        $invoice->update(['status' => 'cancelled']);

        // Assert
        // 1. Allocations should be deleted
        $this->assertDatabaseMissing('collection_allocations', [
            'invoice_id' => $invoice->id,
        ]);

        // 2. Invoice balance and paid_amount should be zeroed
        $this->assertEquals(0, $invoice->fresh()->balance);
        $this->assertEquals(0, $invoice->fresh()->paid_amount);

        // 3. Customer balance should be adjusted
        // Original: 400 (after collection)
        // After cancel: 400 - total (1000) + paid (600) = 0
        // OR simpler: original - invoice.balance = 400 - 400 = 0
        $this->assertEquals(0, $customer->fresh()->balance);
    }

    /**
     * @test
     * BR-INV-004: Deleting invoice throws exception
     */
    public function it_prevents_invoice_deletion(): void
    {
        // Arrange
        $invoice = Invoice::factory()->create();

        // Assert exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن حذف الفواتير');

        // Act
        $invoice->delete();
    }

    /**
     * @test
     * BR-INV-005: Cannot reduce total below paid_amount
     */
    public function it_prevents_reducing_total_below_paid_amount(): void
    {
        // Arrange
        $invoice = Invoice::factory()->create([
            'total' => 1000,
            'paid_amount' => 600,
            'balance' => 400,
        ]);

        // Assert exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن تقليل القيمة أقل من المدفوع');

        // Act - Try to reduce total to less than paid
        $invoice->update(['total' => 500]); // Less than paid_amount (600)
    }

    /**
     * @test
     * Updating total adjusts customer balance by difference
     */
    public function it_adjusts_customer_balance_when_total_changes(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'paid_amount' => 0,
            'balance' => 500,
        ]);

        // Customer balance should be 1500 now (1000 + 500)
        $this->assertEquals(1500, $customer->fresh()->balance);

        // Act - Increase total
        $invoice->update(['total' => 700]);

        // Assert
        $this->assertEquals(700, $invoice->fresh()->balance);
        // Customer balance: 1500 + (700 - 500) = 1700
        $this->assertEquals(1700, $customer->fresh()->balance);
    }

    /**
     * @test
     * Reducing total (but still above paid_amount) works correctly
     */
    public function it_allows_reducing_total_when_above_paid_amount(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 1000,
            'paid_amount' => 200,
            'balance' => 800,
        ]);

        // Act - Reduce total to 600 (still > paid_amount 200)
        $invoice->update(['total' => 600]);

        // Assert
        $this->assertEquals(400, $invoice->fresh()->balance); // 600 - 200
        $this->assertEquals(600, $customer->fresh()->balance); // 1000 - 400
    }

    /**
     * @test
     * BR-INV-003: Cannot reactivate cancelled invoice
     */
    public function it_prevents_reactivating_cancelled_invoice(): void
    {
        // Arrange
        $invoice = Invoice::factory()->create([
            'status' => 'cancelled',
            'balance' => 0,
            'paid_amount' => 0,
        ]);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن إعادة تفعيل فاتورة ملغاة');

        // Act
        $invoice->update(['status' => 'active']);
    }

    /**
     * @test
     * Audit log is created on invoice creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        // Arrange
        $user = $this->actingAsUser();

        // Act
        $invoice = Invoice::factory()->create([
            'created_by' => $user->id,
            'total' => 500,
        ]);

        // Assert
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => 'Invoice',
            'model_id' => $invoice->id,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
    }

    /**
     * @test
     * Edge Case: Cancelling invoice with no payments
     */
    public function it_cancels_unpaid_invoice_cleanly(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 0]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'paid_amount' => 0,
            'balance' => 500,
            'status' => 'active',
        ]);

        // Customer balance after invoice = 500
        $this->assertEquals(500, $customer->fresh()->balance);

        // Act
        $invoice->update(['status' => 'cancelled']);

        // Assert
        $this->assertEquals(0, $invoice->fresh()->balance);
        $this->assertEquals(0, $customer->fresh()->balance); // 500 - 500
    }
}
