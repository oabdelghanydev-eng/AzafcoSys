<?php

namespace Tests\Unit\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\CollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CollectionService Unit Tests
 * 
 * Tests BR-COL-002 through BR-COL-006
 * FIFO allocation logic for payment distribution
 */
class CollectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CollectionService::class);
    }

    /**
     * @test
     * BR-COL-002: FIFO Distribution (Oldest First)
     */
    public function it_allocates_collection_to_oldest_invoice_first(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(5),
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(3),
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 600,
            'distribution_method' => 'manual', // Use manual to prevent Observer auto-allocation
            'status' => 'confirmed',
        ]);

        // Act
        $this->service->allocatePayment($collection);

        // Assert
        $this->assertEquals(0, $invoice1->fresh()->balance, 'Oldest invoice should be fully paid');
        $this->assertEquals(500, $invoice1->fresh()->paid_amount);

        $this->assertEquals(400, $invoice2->fresh()->balance, 'Newer invoice should have 100 paid');
        $this->assertEquals(100, $invoice2->fresh()->paid_amount);

        // Customer balance: 1000 - 600 = 400
        $this->assertEquals(400, $customer->fresh()->balance);

        // Allocations should be recorded
        $this->assertDatabaseHas('collection_allocations', [
            'collection_id' => $collection->id,
            'invoice_id' => $invoice1->id,
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('collection_allocations', [
            'collection_id' => $collection->id,
            'invoice_id' => $invoice2->id,
            'amount' => 100,
        ]);
    }

    /**
     * @test
     * BR-COL-002: FIFO Distribution (Newest First)
     */
    public function it_allocates_collection_to_newest_invoice_first_when_specified(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(5),
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(3),
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
            'distribution_method' => 'manual', // Prevent Observer auto-allocation
        ]);

        // Act
        $this->service->allocatePayment($collection);

        // Assert - Newer invoice should be paid first
        $this->assertEquals(200, $invoice2->fresh()->balance);
        $this->assertEquals(300, $invoice2->fresh()->paid_amount);

        $this->assertEquals(500, $invoice1->fresh()->balance, 'Older invoice untouched');
        $this->assertEquals(0, $invoice1->fresh()->paid_amount);
    }

    /**
     * @test
     * BR-COL-003: Manual Allocation to Specific Invoice
     */
    public function it_allocates_entire_amount_to_specific_invoice_when_manual(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 200,
            'distribution_method' => 'manual',
        ]);

        // Act - Manually allocate to invoice2
        $this->service->allocateToInvoice($collection, $invoice2);

        // Assert - Only invoice2 should be affected
        $this->assertEquals(500, $invoice1->fresh()->balance);
        $this->assertEquals(300, $invoice2->fresh()->balance);
        $this->assertEquals(200, $invoice2->fresh()->paid_amount);
    }

    /**
     * @test
     * BR-COL-004: Skip Cancelled Invoices
     */
    public function it_skips_cancelled_invoices_during_allocation(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(5),
            'total' => 500,
            'balance' => 0, // Cancelled invoices have 0 balance
            'status' => 'cancelled',
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(3),
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
            'distribution_method' => 'manual', // Prevent Observer auto-allocation
        ]);

        // Act
        $this->service->allocatePayment($collection);

        // Assert - Only active invoice should receive payment
        $this->assertDatabaseMissing('collection_allocations', [
            'collection_id' => $collection->id,
            'invoice_id' => $invoice1->id, // Cancelled invoice
        ]);

        $this->assertDatabaseHas('collection_allocations', [
            'collection_id' => $collection->id,
            'invoice_id' => $invoice2->id, // Active invoice
            'amount' => 300,
        ]);
    }

    /**
     * @test
     * BR-COL-005: Race Condition Protection with Database Locks
     */
    public function it_uses_database_locks_to_prevent_race_conditions(): void
    {
        $this->markTestSkipped('Requires concurrent request simulation - manual/integration test');

        // This test would require:
        // 1. Two simultaneous requests
        // 2. Same customer/invoices
        // 3. Verify one waits for the other (lockForUpdate behavior)
        // 4. Verify final state is consistent
    }

    /**
     * @test
     * Edge Case: Collection Amount Exceeds Total Owed
     */
    public function it_handles_overpayment_gracefully(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 300]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 300,
            'balance' => 300,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 500, // More than owed
            'distribution_method' => 'manual', // Prevent Observer auto-allocation
        ]);

        // Act
        $this->service->allocatePayment($collection);

        // Assert
        $this->assertEquals(0, $invoice1->fresh()->balance);
        $this->assertEquals(300, $invoice1->fresh()->paid_amount);

        // Remaining 200 becomes credit balance (negative)
        $this->assertEquals(-200, $customer->fresh()->balance);

        // Only 300 allocated (not 500)
        $this->assertEquals(300, $collection->allocations->sum('amount'));
    }

    /**
     * @test
     * Edge Case: Partial Payment on Multiple Invoices
     */
    public function it_distributes_partial_payment_across_multiple_invoices(): void
    {
        // Arrange
        $customer = Customer::factory()->create(['balance' => 1500]);

        $invoices = collect([
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'date' => now()->subDays(10),
                'total' => 500,
                'balance' => 500,
                'status' => 'active',
            ]),
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'date' => now()->subDays(8),
                'total' => 500,
                'balance' => 500,
                'status' => 'active',
            ]),
            Invoice::factory()->create([
                'customer_id' => $customer->id,
                'date' => now()->subDays(6),
                'total' => 500,
                'balance' => 500,
                'status' => 'active',
            ]),
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 750, // Partial coverage
            'distribution_method' => 'manual', // Prevent Observer auto-allocation
        ]);

        // Act
        $this->service->allocatePayment($collection);

        // Assert
        $this->assertEquals(0, $invoices[0]->fresh()->balance); // 1st fully paid
        $this->assertEquals(0, $invoices[1]->fresh()->balance); // 2nd fully paid
        $this->assertEquals(250, $invoices[2]->fresh()->balance); // 3rd partially paid

        $this->assertEquals(750, $customer->fresh()->balance); // 1500 - 750
    }
}
