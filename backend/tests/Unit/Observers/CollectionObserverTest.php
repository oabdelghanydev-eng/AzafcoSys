<?php

namespace Tests\Unit\Observers;

use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CollectionObserver Unit Tests
 * 
 * Tests critical business rules:
 * - BR-COL-001: Customer balance decrease on collection
 * - BR-COL-006: Cancellation and allocation reversal
 * - BR-COL-007: Deletion prevention
 */
class CollectionObserverTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * BR-COL-001: Collection creation decreases customer balance
     */
    public function it_decreases_customer_balance_when_collection_created(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        // Act
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
            'status' => 'confirmed',
        ]);

        // Assert
        // Balance: 1000 - 300 = 700
        $this->assertEquals(700, $customer->fresh()->balance);
    }

    /**
     * @test
     * BR-COL-006: Cancelling collection reverses customer balance
     */
    public function it_increases_customer_balance_when_collection_cancelled(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 500,
            'status' => 'confirmed',
        ]);

        // Customer balance after collection: 1000 - 500 = 500
        $this->assertEquals(500, $customer->fresh()->balance);

        // Act - Cancel the collection
        $collection->update(['status' => 'cancelled']);

        // Assert
        // Balance should return: 500 + 500 = 1000
        $this->assertEquals(1000, $customer->fresh()->balance);
    }

    /**
     * @test
     * BR-COL-006: Cancelling collection deletes allocations
     */
    public function it_deletes_allocations_when_collection_cancelled(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
        ]);

        $collection = Collection::factory()->manual()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
            'status' => 'confirmed',
        ]);

        // Create allocation
        $allocation = CollectionAllocation::create([
            'collection_id' => $collection->id,
            'invoice_id' => $invoice->id,
            'amount' => 300,
        ]);

        $this->assertDatabaseHas('collection_allocations', [
            'id' => $allocation->id,
        ]);

        // Act - Cancel collection
        $collection->update(['status' => 'cancelled']);

        // Assert - Allocations should be deleted
        $this->assertDatabaseMissing('collection_allocations', [
            'collection_id' => $collection->id,
        ]);
    }

    /**
     * @test
     * BR-COL-007: Deleting collection throws exception
     */
    public function it_prevents_collection_deletion(): void
    {
        // Arrange
        $collection = Collection::factory()->create();

        // Assert exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن حذف التحصيلات');

        // Act
        $collection->delete();
    }

    /**
     * @test
     * Audit log is created on collection creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        // Arrange
        $user = $this->actingAsUser();

        // Act
        $collection = Collection::factory()->create([
            'created_by' => $user->id,
            'amount' => 500,
        ]);

        // Assert
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => 'App\\Models\\Collection', // AuditService stores FQCN
            'model_id' => $collection->id,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
    }

    /**
     * @test
     * Cancellation updates allocations' invoices
     */
    public function it_updates_invoice_balances_when_allocations_deleted(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',  // Required for Observer
        ]);

        $collection = Collection::factory()->manual()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
        ]);

        // Simulate allocation (Observer creates it and updates invoice automatically)
        CollectionAllocation::create([
            'collection_id' => $collection->id,
            'invoice_id' => $invoice->id,
            'amount' => 300,
        ]);

        // Observer automatically updated:
        // invoice.paid_amount = 0 + 300 = 300
        // invoice.balance = 500 - 300 = 200

        // Act - Cancel collection
        $collection->update(['status' => 'cancelled']);

        // Assert
        // When allocation is deleted, CollectionAllocationObserver should:
        // - Decrease invoice.paid_amount by 300
        // - Increase invoice.balance by 300
        $this->assertEquals(0, $invoice->fresh()->paid_amount);
        $this->assertEquals(500, $invoice->fresh()->balance);
    }

    /**
     * @test
     * Multiple allocations are handled correctly
     */
    public function it_handles_multiple_allocations_on_cancellation(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 2000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',  // Required for Observer
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',  // Required for Observer
        ]);

        $collection = Collection::factory()->manual()->create([
            'customer_id' => $customer->id,
            'amount' => 700,
        ]);

        // Create allocations (Observer updates invoices automatically)
        CollectionAllocation::create([
            'collection_id' => $collection->id,
            'invoice_id' => $invoice1->id,
            'amount' => 500,
        ]);

        CollectionAllocation::create([
            'collection_id' => $collection->id,
            'invoice_id' => $invoice2->id,
            'amount' => 200,
        ]);

        // Observer automatically updated invoic invoices:
        // invoice1: paid=500, balance=0
        // invoice2: paid=200, balance=300

        // Act - Cancel collection
        $collection->update(['status' => 'cancelled']);

        // Assert - All allocations deleted, invoices restored
        $this->assertEquals(0, $invoice1->fresh()->paid_amount);
        $this->assertEquals(500, $invoice1->fresh()->balance);

        $this->assertEquals(0, $invoice2->fresh()->paid_amount);
        $this->assertEquals(500, $invoice2->fresh()->balance);
    }

    /**
     * @test
     * Edge Case: Cancelling collection with no allocations
     */
    public function it_cancels_collection_without_allocations_cleanly(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 500]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
            'status' => 'confirmed',
        ]);

        // No allocations created (manual distribution pending)
        $this->assertEquals(200, $customer->fresh()->balance);

        // Act
        $collection->update(['status' => 'cancelled']);

        // Assert
        $this->assertEquals(500, $customer->fresh()->balance);
    }
}
