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

        // Create a return worth 200 EGP (decreases customer balance by 200)
        $return = $returnService->createReturn(
            $this->customer->id,
            [
                [
                    'product_id' => $this->product->id,
                    'cartons' => 2,
                    'unit_price' => 100.00, // 2 cartons * 100 = 200 EGP
                    'shipment_item_id' => $this->shipmentItem->id,
                ],
            ]
        );

        // Customer balance should have decreased by 200
        $this->customer->refresh();
        $this->assertEquals(800.00, (float) $this->customer->balance);

        // Cancel the return
        $returnService->cancelReturn($return);

        // Customer balance should increase by 200 (back to 1000)
        // NOT by 400 (which would happen with double-credit bug)
        $this->customer->refresh();
        $this->assertEquals(1000.00, (float) $this->customer->balance);
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
                    'unit_price' => 50.00,
                    'shipment_item_id' => $this->shipmentItem->id,
                ],
            ]
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

        $return = $returnService->createReturn(
            $this->customer->id,
            [
                [
                    'product_id' => $this->product->id,
                    'cartons' => 1,
                    'unit_price' => 100.00,
                    'shipment_item_id' => $this->shipmentItem->id,
                ],
            ]
        );

        $this->assertEquals('active', $return->status);

        $returnService->cancelReturn($return);

        $return->refresh();
        $this->assertEquals('cancelled', $return->status);
        $this->assertNotNull($return->cancelled_at);
    }
}
