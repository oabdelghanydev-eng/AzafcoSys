<?php

namespace Tests\Debug;

use App\Models\Collection;
use App\Models\CollectionAllocation;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickAllocationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function debug_allocation_deletion()
    {
        $customer = Customer::factory()->create(['balance' => 1000]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',
        ]);

        $collection = Collection::factory()->manual()->create([
            'customer_id' => $customer->id,
            'amount' => 300,
        ]);

        // Create allocation
        $allocation = CollectionAllocation::create([
            'collection_id' => $collection->id,
            'invoice_id' => $invoice->id,
            'amount' => 300,
        ]);

        echo "\nAfter allocation creation:\n";
        echo "- paid_amount: " . $invoice->fresh()->paid_amount . "\n";
        echo "- balance: " . $invoice->fresh()->balance . "\n";

        // Delete allocation
        $allocation->delete();

        echo "\nAfter allocation deletion:\n";
        echo "- paid_amount: " . $invoice->fresh()->paid_amount . "\n";
        echo "- balance: " . $invoice->fresh()->balance . "\n";

        $this->assertEquals(0, $invoice->fresh()->paid_amount);
        $this->assertEquals(500, $invoice->fresh()->balance);
    }
}
