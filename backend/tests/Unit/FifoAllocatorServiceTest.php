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
 * FIFO Allocation Service Tests (Cartons-Based)
 * Updated 2025-12-19: Now uses cartons for allocation instead of weight
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
        $this->createShipmentWithItem(100, 10.00, 1); // 100 cartons

        $allocations = $this->fifoService->allocate($this->product->id, 50); // allocate 50 cartons

        $this->assertCount(1, $allocations);
        $this->assertEquals(50, $allocations->first()['cartons']);
    }

    public function test_allocate_from_multiple_shipments(): void
    {
        // First shipment: 30 cartons
        $this->createShipmentWithItem(30, 10.00, 1);
        // Second shipment: 50 cartons
        $this->createShipmentWithItem(50, 12.00, 2);

        // Allocate 60 cartons (should take 30 from first, 30 from second)
        $allocations = $this->fifoService->allocate($this->product->id, 60);

        $this->assertCount(2, $allocations);
        $this->assertEquals(30, $allocations[0]['cartons']);
        $this->assertEquals(30, $allocations[1]['cartons']);
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
            'cartons' => 100,
            'sold_cartons' => 0,
        ]);

        // Create shipment 1 second (will get fifo_sequence=2 from boot)
        $shipment1 = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'open',
        ]);
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment1->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 0,
        ]);

        // Force fifo_sequence via DB update (bypass model events)
        // Make shipment1 have lower sequence (should be first in FIFO)
        \DB::table('shipments')->where('id', $shipment1->id)->update(['fifo_sequence' => 1]);
        \DB::table('shipments')->where('id', $shipment2->id)->update(['fifo_sequence' => 2]);

        // Allocate 50 cartons - should come from shipment1 (fifo_sequence=1)
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
        $this->createShipmentWithItem(50, 10.00, 1); // 50 cartons

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/الكراتين المطلوبة غير متوفرة/');

        $this->fifoService->allocate($this->product->id, 100); // requesting 100 cartons
    }

    public function test_returns_available_amount_in_exception_message(): void
    {
        $this->createShipmentWithItem(30, 10.00, 1); // 30 cartons

        try {
            $this->fifoService->allocate($this->product->id, 50); // requesting 50 cartons
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

        $this->assertEquals(150, $available); // 100 + 50 cartons
    }

    public function test_get_available_stock_excludes_settled_shipments(): void
    {
        // Open shipment: 100 cartons
        $this->createShipmentWithItem(100, 10.00, 1, 'open');
        // Settled shipment: 50 cartons (should not count)
        $this->createShipmentWithItem(50, 12.00, 2, 'settled');

        $available = $this->fifoService->getAvailableStock($this->product->id);

        $this->assertEquals(100, $available);
    }

    public function test_get_available_stock_includes_closed_shipments(): void
    {
        // Open shipment: 100 cartons
        $this->createShipmentWithItem(100, 10.00, 1, 'open');
        // Closed shipment: 50 cartons (should count)
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
        $this->assertEquals(100, $allocations->first()['cartons']);
    }

    public function test_allocate_from_partially_sold_item(): void
    {
        $shipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'open',
            'fifo_sequence' => 1,
        ]);

        // Item with 100 cartons, 30 already sold
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 30, // 70 remaining
            'unit_cost' => 10.00,
        ]);

        $allocations = $this->fifoService->allocate($this->product->id, 50);

        $this->assertEquals(50, $allocations->first()['cartons']);
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createShipmentWithItem(
        int $cartons,
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
            'cartons' => $cartons,
            'sold_cartons' => 0,
            'unit_cost' => $unitCost,
        ]);

        return $shipment;
    }
}

