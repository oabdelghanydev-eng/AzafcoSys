<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Return Cancellation Tests
 * 
 * ARCHITECTURAL DECISION (2025-12-27):
 * Validates that return cancellation logic executes EXACTLY ONCE
 * through ReturnService, preventing the double-credit bug.
 * 
 * This is a regression test to ensure the bug is never reintroduced.
 */
class ReturnCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Product $product;
    private Shipment $shipment;
    private ShipmentItem $shipmentItem;
    private DailyReport $dailyReport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);

        // Create customer with initial balance
        $this->customer = Customer::factory()->create(['balance' => 1000.00]);

        // Create product and shipment
        $this->product = Product::factory()->create();
        $this->shipment = Shipment::factory()->create(['status' => 'open']);
        $this->shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $this->shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 50, // 50 already sold
            'weight_per_unit' => 10.5,
        ]);

        // Create open daily report
        $this->dailyReport = DailyReport::factory()->create([
            'status' => 'open',
            'date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Test that cancelling a return updates customer balance exactly once.
     * 
     * REGRESSION TEST for double-credit bug where:
     * - ReturnService.cancelReturn() incremented balance
     * - ReturnObserver.handleCancellation() ALSO incremented balance
     */
    public function test_cancel_return_updates_customer_balance_exactly_once(): void
    {
        $returnService = app(ReturnService::class);

        // Create an invoice first (required for createReturn)
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        // Create invoice item linked to shipment item
        $invoiceItem = \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'shipment_item_id' => $this->shipmentItem->id,
            'shipment_id' => $this->shipment->id,
            'cartons' => 10,
            'quantity' => 105, // 10 cartons * 10.5 weight
            'unit_price' => 50.00,
            'subtotal' => 500.00,
        ]);

        // Update customer balance after invoice
        $this->customer->update(['balance' => 1500.00]); // 1000 + 500 invoice

        // Create a return worth 105 EGP (2 cartons * 10.5 weight * 50/kg = 1050, but we use total weight*price)
        $return = $returnService->createReturn(
            $this->customer->id,
            [
                [
                    'product_id' => $this->product->id,
                    'cartons' => 2,
                    'quantity' => 21.0,  // 2 cartons * 10.5 weight per carton
                    'unit_price' => 50.00,  // Must match invoice item unit_price
                    'shipment_item_id' => $this->shipmentItem->id,
                ],
            ],
            $invoice->id  // Required invoice ID
        );

        // Customer balance should have decreased by return amount
        $this->customer->refresh();
        $returnAmount = 21.0 * 50.00;  // quantity * unit_price = 1050
        $expectedAfterReturn = 1500.00 - $returnAmount;
        $this->assertEquals($expectedAfterReturn, (float) $this->customer->balance);

        // Cancel the return
        $returnService->cancelReturn($return);

        // Customer balance should increase by return amount (back to 1500)
        // NOT by double (which would happen with double-credit bug)
        $this->customer->refresh();
        $this->assertEquals(1500.00, (float) $this->customer->balance);
    }

    /**
     * Test that cancelling a return updates inventory exactly once.
     * 
     * REGRESSION TEST for double-credit bug where:
     * - ReturnService.cancelReturn() incremented sold_cartons by 'cartons'
     * - ReturnObserver.handleCancellation() ALSO incremented by 'quantity' (WRONG FIELD!)
     */
    public function test_cancel_return_updates_inventory_exactly_once(): void
    {
        $returnService = app(ReturnService::class);

        // Create an invoice first (required for createReturn)
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        // Create invoice item linked to shipment item
        $invoiceItem = \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'shipment_item_id' => $this->shipmentItem->id,
            'shipment_id' => $this->shipment->id,
            'cartons' => 10,
            'quantity' => 105, // 10 cartons * 10.5 weight
            'unit_price' => 50.00,
            'subtotal' => 500.00,
        ]);

        // Initial state: 50 sold_cartons
        $initialSoldCartons = $this->shipmentItem->sold_cartons;
        $this->assertEquals(50, $initialSoldCartons);

        // Create a return for 5 cartons (decreases sold_cartons by 5)
        $return = $returnService->createReturn(
            $this->customer->id,
            [
                [
                    'product_id' => $this->product->id,
                    'cartons' => 5,
                    'quantity' => 52.5,  // 5 cartons * 10.5 weight
                    'unit_price' => 50.00,
                    'shipment_item_id' => $this->shipmentItem->id,
                ],
            ],
            $invoice->id  // Required invoice ID
        );

        // sold_cartons should have decreased by 5
        $this->shipmentItem->refresh();
        $this->assertEquals(45, $this->shipmentItem->sold_cartons);

        // Cancel the return
        $returnService->cancelReturn($return);

        // sold_cartons should increase by 5 (back to 50)
        // NOT by a weight value (quantity) which was the bug
        $this->shipmentItem->refresh();
        $this->assertEquals(50, $this->shipmentItem->sold_cartons);
    }

    /**
     * Test that return status is properly updated after cancellation.
     */
    public function test_cancel_return_updates_status(): void
    {
        $returnService = app(ReturnService::class);

        // Create an invoice first (required for createReturn)
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        // Create invoice item linked to shipment item
        $invoiceItem = \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'shipment_item_id' => $this->shipmentItem->id,
            'shipment_id' => $this->shipment->id,
            'cartons' => 10,
            'quantity' => 105,
            'unit_price' => 50.00,
            'subtotal' => 500.00,
        ]);

        $return = $returnService->createReturn(
            $this->customer->id,
            [
                [
                    'product_id' => $this->product->id,
                    'cartons' => 1,
                    'quantity' => 10.5,  // 1 carton * 10.5 weight
                    'unit_price' => 50.00,
                    'shipment_item_id' => $this->shipmentItem->id,
                ],
            ],
            $invoice->id  // Required invoice ID
        );

        $this->assertEquals('active', $return->status);

        $returnService->cancelReturn($return);

        $return->refresh();
        $this->assertEquals('cancelled', $return->status);
        $this->assertNotNull($return->cancelled_at);
    }
}
