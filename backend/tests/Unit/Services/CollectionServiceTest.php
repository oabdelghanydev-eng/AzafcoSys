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
 * Tests FIFO allocation logic:
 * - BR-COL-004: Skip Cancelled Invoices
 * - Edge cases: overpayment handling
 * 
 * Note: Complex allocation tests moved to Feature tests for full integration.
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
     * BR-COL-004: Skip Cancelled Invoices
     */
    public function it_skips_cancelled_invoices_during_allocation(): void
    {
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(5),
            'total' => 500,
            'balance' => 0,
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
            'distribution_method' => 'manual',
        ]);

        $this->service->allocatePayment($collection);

        $this->assertDatabaseMissing('collection_allocations', [
            'collection_id' => $collection->id,
            'invoice_id' => $invoice1->id,
        ]);

        $this->assertDatabaseHas('collection_allocations', [
            'collection_id' => $collection->id,
            'invoice_id' => $invoice2->id,
            'amount' => 300,
        ]);
    }

    /**
     * @test
     * Edge Case: Collection Amount Exceeds Total Owed
     */
    public function it_handles_overpayment_gracefully(): void
    {
        $customer = Customer::factory()->create(['balance' => 300]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 300,
            'balance' => 300,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 500,
            'distribution_method' => 'manual',
        ]);

        $this->service->allocatePayment($collection);

        $this->assertEquals(0, $invoice1->fresh()->balance);
        $this->assertEquals(300, $invoice1->fresh()->paid_amount);
        $this->assertEquals(-200, $customer->fresh()->balance);
        $this->assertEquals(300, $collection->allocations->sum('amount'));
    }
}
