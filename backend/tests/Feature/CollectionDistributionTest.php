<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Collection;
use App\Services\CollectionDistributorService;

/**
 * Feature tests for Collection Distribution
 * تحسين 2025-12-13: اختبارات توزيع التحصيلات
 */
class CollectionDistributionTest extends TestCase
{
    use RefreshDatabase;

    private CollectionDistributorService $distributorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->distributorService = app(CollectionDistributorService::class);
    }

    /**
     * Test FIFO (oldest_first) distribution allocates to oldest invoices first
     */
    public function test_fifo_distribution_allocates_oldest_first(): void
    {
        $customer = Customer::factory()->create(['balance' => 300]);

        // Create 3 invoices with different dates
        $oldestInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => '2024-01-01',
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        $middleInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => '2024-02-01',
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        $newestInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => '2024-03-01',
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        // Create collection with auto (FIFO - oldest first)
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 150,
            'distribution_method' => 'auto',
        ]);

        // Refresh invoices
        $oldestInvoice->refresh();
        $middleInvoice->refresh();
        $newestInvoice->refresh();

        // Oldest should be fully paid (100 of 150)
        $this->assertEquals(0, $oldestInvoice->balance);

        // Middle should be partially paid (50 of 100)
        $this->assertEquals(50, $middleInvoice->balance);

        // Newest should be untouched
        $this->assertEquals(100, $newestInvoice->balance);
    }

    /**
     * Test LIFO (newest_first) distribution allocates to newest invoices first
     * Note: This test requires MySQL (SQLite doesn't support modified ENUM)
     */
    public function test_lifo_distribution_allocates_newest_first(): void
    {
        // Skip if using SQLite (doesn't support new enum values)
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('LIFO test requires MySQL database');
        }

        $customer = Customer::factory()->create(['balance' => 300]);

        // Create 3 invoices with different dates
        $oldestInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => '2024-01-01',
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        $middleInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => '2024-02-01',
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        $newestInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => '2024-03-01',
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        // Create collection with newest_first
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 150,
            'distribution_method' => 'newest_first',
        ]);

        // Refresh invoices
        $oldestInvoice->refresh();
        $middleInvoice->refresh();
        $newestInvoice->refresh();

        // Newest should be fully paid (100 of 150)
        $this->assertEquals(0, $newestInvoice->balance);

        // Middle should be partially paid (50 of 100)
        $this->assertEquals(50, $middleInvoice->balance);

        // Oldest should be untouched
        $this->assertEquals(100, $oldestInvoice->balance);
    }

    /**
     * Test manual distribution does not auto-allocate
     */
    public function test_manual_distribution_does_not_auto_allocate(): void
    {
        $customer = Customer::factory()->create();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100,
            'balance' => 100,
            'status' => 'active',
        ]);

        // Create collection with manual distribution
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 100,
            'distribution_method' => 'manual',
            'allocated_amount' => 0,
            'unallocated_amount' => 100,
        ]);

        $invoice->refresh();

        // Invoice should still have full balance (no auto-allocation happened)
        $this->assertEquals(100.0, (float) $invoice->balance);

        // No allocations should exist
        $this->assertEquals(0, $collection->allocations()->count());
    }
}
