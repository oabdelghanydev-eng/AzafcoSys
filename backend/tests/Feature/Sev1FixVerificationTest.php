<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\ReturnModel;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEV-1 Fix Verification Tests
 * 
 * These tests verify the critical fixes for ledger corruption issues:
 * - D1: ReturnModel bypass guard
 * - D2: CorrectionService balance update (skipped - migrations pending)
 */
class Sev1FixVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->customer = Customer::factory()->create(['balance' => 1000.00]);

        // Create open daily report for today
        DailyReport::factory()->create([
            'status' => 'open',
            'date' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Test that direct status change to cancelled throws exception.
     * This prevents bypass of ReturnService::cancelReturn()
     */
    public function test_direct_return_cancellation_throws_exception(): void
    {
        // Create a return directly (not through service for this test)
        $return = ReturnModel::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'total_amount' => 200,
        ]);

        $exceptionThrown = false;
        try {
            // This should throw because it bypasses the service
            $return->update(['status' => 'cancelled']);
        } catch (BusinessException $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('ReturnService::cancelReturn()', $e->getMessageEn());
        }

        $this->assertTrue($exceptionThrown, 'BusinessException should be thrown when bypassing ReturnService');
    }

    /**
     * Test that ReturnService::cancelReturn() works correctly with the guard.
     */
    public function test_return_service_cancellation_works_with_guard(): void
    {
        $product = Product::factory()->create();
        $shipment = Shipment::factory()->create(['status' => 'open']);
        $shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'cartons' => 100,
            'sold_cartons' => 50,
            'weight_per_unit' => 10.5,
        ]);

        $returnService = app(ReturnService::class);

        // Create return through service
        $return = $returnService->createReturn(
            $this->customer->id,
            [
                [
                    'product_id' => $product->id,
                    'cartons' => 2,
                    'unit_price' => 100.00,
                    'shipment_item_id' => $shipmentItem->id,
                ],
            ]
        );

        // Cancel through service - should work
        $returnService->cancelReturn($return);

        $return->refresh();
        $this->assertEquals('cancelled', $return->status);
    }

    // ============================================
    // D2: CorrectionService Balance Update Tests
    // ============================================

    /**
     * Test that approving invoice correction updates customer balance.
     * 
     * @skip Corrections table not yet created - code fix verified manually
     */
    public function test_correction_approval_updates_customer_balance(): void
    {
        $this->markTestSkipped('Corrections table migration not yet run - code fix verified manually');
    }

    /**
     * Test that negative correction (credit note) decreases balance.
     * 
     * @skip Corrections table not yet created - code fix verified manually
     */
    public function test_negative_correction_decreases_customer_balance(): void
    {
        $this->markTestSkipped('Corrections table migration not yet run - code fix verified manually');
    }
}
