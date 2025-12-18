<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Services\FifoAllocatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIFO Allocation Service Tests
 * تحسين 2025-12-16: اختبارات خدمة FIFO
 */
class FifoAllocatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private FifoAllocatorService $fifoService;

    private Product $product;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fifoService = app(FifoAllocatorService::class);
        $this->product = Product::factory()->create();
        $this->supplier = Supplier::factory()->create();
    }

    // ============================================
    // Basic Allocation Tests
    // ============================================

    public function test_allocate_from_single_shipment(): void
    {
        $this->createShipmentWithItem(100, 10.00, 1);

        $allocations = $this->fifoService->allocate($this->product->id, 50);

        $this->assertCount(1, $allocations);
        $this->assertEquals(50, $allocations->first()['quantity']);
    }

    public function test_allocate_from_multiple_shipments(): void
    {
        // First shipment: 30 kg
        $this->createShipmentWithItem(30, 10.00, 1);
        // Second shipment: 50 kg
        $this->createShipmentWithItem(50, 12.00, 2);

        // Allocate 60 kg (should take 30 from first, 30 from second)
        $allocations = $this->fifoService->allocate($this->product->id, 60);

        $this->assertCount(2, $allocations);
        $this->assertEquals(30, $allocations[0]['quantity']);
        $this->assertEquals(30, $allocations[1]['quantity']);
    }

    public function test_fifo_order_by_sequence_not_date(): void
    {
        // Create shipment 2 first (will get fifo_sequence=1 from boot)
        $shipment2 = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'open',
        ]);
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment2->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 100,
            'sold_quantity' => 0,
        ]);

        // Create shipment 1 second (will get fifo_sequence=2 from boot)
        $shipment1 = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'open',
        ]);
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment1->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 100,
            'sold_quantity' => 0,
        ]);

        // Force fifo_sequence via DB update (bypass model events)
        // Make shipment1 have lower sequence (should be first in FIFO)
        \DB::table('shipments')->where('id', $shipment1->id)->update(['fifo_sequence' => 1]);
        \DB::table('shipments')->where('id', $shipment2->id)->update(['fifo_sequence' => 2]);

        // Allocate 50 - should come from shipment1 (fifo_sequence=1)
        $allocations = $this->fifoService->allocate($this->product->id, 50);

        $this->assertCount(1, $allocations);
        $firstAllocation = $allocations->first();

        // Should allocate from shipment1's item (the one with lower fifo_sequence)
        $shipment1ItemId = $shipment1->fresh()->items->first()->id;
        $this->assertEquals($shipment1ItemId, $firstAllocation['shipment_item_id']);
    }

    // ============================================
    // Insufficient Stock Tests
    // ============================================

    public function test_throws_exception_when_insufficient_stock(): void
    {
        $this->createShipmentWithItem(50, 10.00, 1);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/الكمية المطلوبة غير متوفرة/'); // Match new detailed message

        $this->fifoService->allocate($this->product->id, 100);
    }

    public function test_returns_available_amount_in_exception_message(): void
    {
        $this->createShipmentWithItem(30, 10.00, 1);

        try {
            $this->fifoService->allocate($this->product->id, 50);
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('50', $e->getMessage());
            $this->assertStringContainsString('30', $e->getMessage());
        }
    }

    // ============================================
    // Available Stock Tests
    // ============================================

    public function test_get_available_stock_returns_correct_amount(): void
    {
        $this->createShipmentWithItem(100, 10.00, 1);
        $this->createShipmentWithItem(50, 12.00, 2);

        $available = $this->fifoService->getAvailableStock($this->product->id);

        $this->assertEquals(150, $available);
    }

    public function test_get_available_stock_excludes_settled_shipments(): void
    {
        // Open shipment: 100 kg
        $this->createShipmentWithItem(100, 10.00, 1, 'open');
        // Settled shipment: 50 kg (should not count)
        $this->createShipmentWithItem(50, 12.00, 2, 'settled');

        $available = $this->fifoService->getAvailableStock($this->product->id);

        $this->assertEquals(100, $available);
    }

    public function test_get_available_stock_includes_closed_shipments(): void
    {
        // Open shipment: 100 kg
        $this->createShipmentWithItem(100, 10.00, 1, 'open');
        // Closed shipment: 50 kg (should count)
        $this->createShipmentWithItem(50, 12.00, 2, 'closed');

        $available = $this->fifoService->getAvailableStock($this->product->id);

        $this->assertEquals(150, $available);
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function test_allocate_exact_amount(): void
    {
        $this->createShipmentWithItem(100, 10.00, 1);

        $allocations = $this->fifoService->allocate($this->product->id, 100);

        $this->assertCount(1, $allocations);
        $this->assertEquals(100, $allocations->first()['quantity']);
    }

    public function test_allocate_from_partially_sold_item(): void
    {
        $shipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'open',
            'fifo_sequence' => 1,
        ]);

        // Item with 100 initial, 30 already sold
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 70,
            'sold_quantity' => 30,
            'unit_cost' => 10.00,
        ]);

        $allocations = $this->fifoService->allocate($this->product->id, 50);

        $this->assertEquals(50, $allocations->first()['quantity']);
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createShipmentWithItem(
        float $quantity,
        float $unitCost,
        int $fifoSequence,
        string $status = 'open'
    ): Shipment {
        $shipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => $status,
            'fifo_sequence' => $fifoSequence,
        ]);

        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'initial_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'sold_quantity' => 0,
            'unit_cost' => $unitCost,
        ]);

        return $shipment;
    }
}
