<?php

namespace Tests\Feature\Scenarios;

use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FifoAllocatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIFO Allocation Scenario Tests
 * 
 * Tests real-world FIFO scenarios to ensure oldest items are sold first
 */
class FifoAllocationScenarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Customer $customer;
    private Product $product;
    private FifoAllocatorService $fifoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->supplier = Supplier::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create();
        $this->fifoService = app(FifoAllocatorService::class);

        $this->actingAs($this->admin);
    }

    /**
     * @test
     * FIFO: Single source allocation
     */
    public function it_allocates_from_single_shipment(): void
    {
        // Arrange: One shipment with 100 cartons
        $shipment = $this->createShipment('SHP-001', 1);
        $this->addItems($shipment, 100);

        // Act: Allocate 50 cartons
        $allocations = $this->fifoService->allocate($this->product->id, 50);

        // Assert
        $this->assertCount(1, $allocations);
        $this->assertEquals(50, $allocations->first()['cartons']);
        $this->assertEquals('SHP-001', $allocations->first()['shipment_number']);
    }

    /**
     * @test
     * FIFO: Multi-source allocation (oldest first)
     */
    public function it_allocates_from_oldest_shipment_first(): void
    {
        // Arrange: Two shipments
        $shipment1 = $this->createShipment('SHP-OLD', 1); // fifo_sequence = 1
        $this->addItems($shipment1, 30);

        $shipment2 = $this->createShipment('SHP-NEW', 2); // fifo_sequence = 2
        $this->addItems($shipment2, 70);

        // Act: Allocate 80 cartons
        $allocations = $this->fifoService->allocate($this->product->id, 80);

        // Assert: Should take 30 from SHP-OLD, 50 from SHP-NEW
        $this->assertCount(2, $allocations);

        // First allocation should be from oldest shipment
        $this->assertEquals('SHP-OLD', $allocations[0]['shipment_number']);
        $this->assertEquals(30, $allocations[0]['cartons']);

        // Second allocation from newer shipment
        $this->assertEquals('SHP-NEW', $allocations[1]['shipment_number']);
        $this->assertEquals(50, $allocations[1]['cartons']);
    }

    /**
     * @test
     * FIFO: Exact quantity match
     */
    public function it_allocates_exact_quantity_available(): void
    {
        // Arrange: 100 cartons available
        $shipment = $this->createShipment('SHP-001', 1);
        $this->addItems($shipment, 100);

        // Act: Allocate exactly 100
        $allocations = $this->fifoService->allocate($this->product->id, 100);

        // Assert
        $this->assertEquals(100, $allocations->sum('cartons'));

        // Note: allocate() only returns allocations, doesn't update DB
        // Stock update happens in allocateAndCreate()
    }

    /**
     * @test
     * FIFO: Insufficient stock throws exception
     */
    public function it_throws_exception_when_insufficient_stock(): void
    {
        // Arrange: Only 100 cartons available
        $shipment = $this->createShipment('SHP-001', 1);
        $this->addItems($shipment, 100);

        // Assert & Act
        $this->expectException(\App\Exceptions\BusinessException::class);
        $this->expectExceptionMessage('الكراتين المطلوبة غير متوفرة');

        // Try to allocate 150 (more than available)
        $this->fifoService->allocate($this->product->id, 150);
    }

    /**
     * @test
     * FIFO: Carryover items follow FIFO sequence
     */
    public function it_includes_carryover_items_in_fifo_order(): void
    {
        // Arrange: Shipment with only carryover (no new cartons)
        $shipment1 = $this->createShipment('SHP-CARRYOVER', 1);
        ShipmentItem::create([
            'shipment_id' => $shipment1->id,
            'product_id' => $this->product->id,
            'cartons' => 0,  // No new cartons
            'carryover_in_cartons' => 30, // Carryover from previous
            'sold_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Newer shipment with new cartons
        $shipment2 = $this->createShipment('SHP-NEW', 2);
        $this->addItems($shipment2, 50);

        // Act: Allocate 60 cartons
        $allocations = $this->fifoService->allocate($this->product->id, 60);

        // Assert: Carryover should be used first (FIFO sequence 1)
        $this->assertCount(2, $allocations);
        $this->assertEquals('SHP-CARRYOVER', $allocations[0]['shipment_number']);
        $this->assertEquals(30, $allocations[0]['cartons']);

        $this->assertEquals('SHP-NEW', $allocations[1]['shipment_number']);
        $this->assertEquals(30, $allocations[1]['cartons']);
    }

    /**
     * @test
     * FIFO: Skips settled shipments
     */
    public function it_skips_settled_shipments(): void
    {
        // Arrange: Settled shipment (should be skipped)
        $settledShipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'number' => 'SHP-SETTLED',
            'fifo_sequence' => 1,
            'status' => 'settled',
        ]);
        ShipmentItem::create([
            'shipment_id' => $settledShipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 50,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Open shipment
        $openShipment = $this->createShipment('SHP-OPEN', 2);
        $this->addItems($openShipment, 80);

        // Act: Allocate 30 cartons
        $allocations = $this->fifoService->allocate($this->product->id, 30);

        // Assert: Should only come from open shipment
        $this->assertCount(1, $allocations);
        $this->assertEquals('SHP-OPEN', $allocations[0]['shipment_number']);
    }

    /**
     * @test
     * FIFO: Respects already sold cartons
     */
    public function it_respects_already_sold_cartons(): void
    {
        // Arrange: Shipment with some cartons already sold
        $shipment = $this->createShipment('SHP-001', 1);
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 70,  // 70 already sold
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Only 30 remaining
        $available = $this->fifoService->getAvailableStock($this->product->id);
        $this->assertEquals(30, $available);

        // Act: Allocate 30 cartons
        $allocations = $this->fifoService->allocate($this->product->id, 30);

        // Assert
        $this->assertEquals(30, $allocations->sum('cartons'));
    }

    /**
     * @test
     * FIFO: Multiple products don't interfere
     */
    public function it_allocates_products_independently(): void
    {
        // Arrange: Two products
        $product2 = Product::factory()->create();

        $shipment = $this->createShipment('SHP-001', 1);

        // Product 1: 100 cartons
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 0,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Product 2: 50 cartons
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $product2->id,
            'cartons' => 50,
            'sold_cartons' => 0,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 3.0,
        ]);

        // Act: Sell 80 of product 1
        $allocations1 = $this->fifoService->allocate($this->product->id, 80);

        // Assert: Product 2 stock unchanged
        $available2 = $this->fifoService->getAvailableStock($product2->id);
        $this->assertEquals(50, $available2);

        // Product 1 has 20 remaining (100 - 80)
        $available1 = $this->fifoService->getAvailableStock($this->product->id);
        $this->assertEquals(100, $available1); // Not updated until allocateAndCreate
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════

    private function createShipment(string $number, int $fifoSequence): Shipment
    {
        return Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'number' => $number,
            'fifo_sequence' => $fifoSequence,
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);
    }

    private function addItems(Shipment $shipment, int $cartons): void
    {
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => $cartons,
            'sold_cartons' => 0,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);
    }
}
