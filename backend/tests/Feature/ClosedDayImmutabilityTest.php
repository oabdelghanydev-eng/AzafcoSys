<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closed Day Immutability Tests
 * 
 * ARCHITECTURAL DECISION (2025-12-27):
 * Validates that transactions on closed fiscal days cannot be modified.
 * This ensures audit compliance and prevents retroactive data corruption.
 */
class ClosedDayImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Supplier $supplier;
    private DailyReport $closedReport;
    private DailyReport $openReport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->customer = Customer::factory()->create();
        $this->supplier = Supplier::factory()->create();

        // Create a closed daily report for yesterday
        $this->closedReport = DailyReport::factory()->create([
            'status' => 'closed',
            'date' => now()->subDay()->format('Y-m-d'),
        ]);

        // Create an open daily report for today
        $this->openReport = DailyReport::factory()->create([
            'status' => 'open',
            'date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Test that invoices on closed days cannot be updated.
     */
    public function test_cannot_update_invoice_on_closed_day(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => $this->closedReport->date,
            'status' => 'active',
            'total' => 100,
        ]);

        $exceptionThrown = false;
        try {
            $invoice->update(['notes' => 'Updated notes']);
        } catch (BusinessException $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('closed day', $e->getMessageEn());
        }

        $this->assertTrue($exceptionThrown, 'BusinessException should have been thrown for closed day update');
    }

    /**
     * Test that invoices on open days can be updated.
     */
    public function test_can_update_invoice_on_open_day(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => $this->openReport->date,
            'status' => 'active',
            'total' => 100,
        ]);

        // Should not throw exception
        $invoice->update(['notes' => 'Updated notes']);

        $this->assertEquals('Updated notes', $invoice->fresh()->notes);
    }

    /**
     * Test that collections on closed days cannot be updated.
     */
    public function test_cannot_update_collection_on_closed_day(): void
    {
        $collection = Collection::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => $this->closedReport->date,
            'amount' => 500,
        ]);

        $this->expectException(BusinessException::class);

        $collection->update(['notes' => 'Updated']);
    }

    /**
     * Test that expenses on closed days cannot be updated.
     */
    public function test_cannot_update_expense_on_closed_day(): void
    {
        $expense = Expense::factory()->create([
            'date' => $this->closedReport->date,
            'amount' => 200,
        ]);

        $this->expectException(BusinessException::class);

        $expense->update(['notes' => 'Updated']);
    }

    /**
     * Test that returns on closed days cannot be updated.
     */
    public function test_cannot_update_return_on_closed_day(): void
    {
        $return = ReturnModel::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => $this->closedReport->date,
            'total_amount' => 150,
            'status' => 'active',
        ]);

        $this->expectException(BusinessException::class);

        $return->update(['notes' => 'Updated']);
    }

    /**
     * Test that transactions without a corresponding daily report can still be modified.
     * (This handles edge case where DailyReport doesn't exist for a date)
     */
    public function test_can_update_transaction_with_no_daily_report(): void
    {
        // Create invoice for a date with no daily report
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => now()->subWeek()->format('Y-m-d'), // No report for this date
            'status' => 'active',
            'total' => 100,
        ]);

        // Should not throw exception - no closed report means not blocked
        $invoice->update(['notes' => 'Updated notes']);

        $this->assertEquals('Updated notes', $invoice->fresh()->notes);
    }

    /**
     * Test that reopening a day allows modifications again.
     */
    public function test_can_update_after_day_reopened(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'date' => $this->closedReport->date,
            'status' => 'active',
            'total' => 100,
        ]);

        // Reopen the day
        $this->closedReport->update(['status' => 'open']);

        // Now should be able to update
        $invoice->update(['notes' => 'Updated after reopen']);

        $this->assertEquals('Updated after reopen', $invoice->fresh()->notes);
    }
}
