<?php

namespace Tests\Unit\Services;

use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ShipmentService Unit Tests
 * 
 * Tests critical business rules:
 * - BR-SHP-001: Shipment status flow
 * - BR-SHP-003: Settlement logic
 * - BR-SHP-004: Unsettle with carryover reversal
 */
class ShipmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ShipmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShipmentService::class);
    }

    /**
     * @test
     * BR-SHP-003: Settle shipment and carryover remaining items
     */
    public function it_settles_shipment_and_carryovers_remaining_quantity(): void
    {
        // Arrange
        $supplier = Supplier::factory()->create();

        $shipment1 = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'closed',
        ]);

        $shipment2 = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $item1 = ShipmentItem::factory()->create([
            'shipment_id' => $shipment1->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 30, // 30kg left
            'sold_quantity' => 70,
        ]);

        // Act
        $this->service->settle($shipment1, $shipment2->id);

        // Assert
        // Shipment 1 should be settled
        $this->assertEquals('settled', $shipment1->fresh()->status);

        // Carryover should be created
        $this->assertDatabaseHas('carryovers', [
            'from_shipment_id' => $shipment1->id,
            'to_shipment_id' => $shipment2->id,
            'product_id' => $item1->product_id,
            'quantity' => 30,
        ]);

        // New item in shipment2
        $this->assertDatabaseHas('shipment_items', [
            'shipment_id' => $shipment2->id,
            'product_id' => $item1->product_id,
            'carryover_in_quantity' => 30,
        ]);
    }

    /**
     * @test
     * BR-SHP-004: Unsettle reverses carryovers
     */
    public function it_reverses_carryovers_when_unsettling_shipment(): void
    {
        // Arrange
        $supplier = Supplier::factory()->create();

        $shipment1 = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
        ]);

        $shipment2 = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $item1 = ShipmentItem::factory()->create([
            'shipment_id' => $shipment1->id,
            'remaining_quantity' => 0, // Already settled
            'carryover_out_quantity' => 25,
        ]);

        $item2 = ShipmentItem::factory()->create([
            'shipment_id' => $shipment2->id,
            'product_id' => $item1->product_id,
            'remaining_quantity' => 25,
            'carryover_in_quantity' => 25,
        ]);

        \App\Models\Carryover::create([
            'from_shipment_id' => $shipment1->id,
            'from_shipment_item_id' => $item1->id,
            'to_shipment_id' => $shipment2->id,
            'to_shipment_item_id' => $item2->id,
            'product_id' => $item1->product_id,
            'quantity' => 25,
            'cartons' => 5,
            'weight_per_unit' => 5,
            'created_by' => 1,
        ]);

        // Act
        $this->service->unsettle($shipment1);

        // Assert
        // Shipment1 should be closed (not settled)
        $this->assertEquals('closed', $shipment1->fresh()->status);

        // Carryover should be deleted
        $this->assertDatabaseMissing('carryovers', [
            'from_shipment_id' => $shipment1->id,
        ]);

        // Item in shipment2 should have carryover removed
        $this->assertEquals(0, $item2->fresh()->remaining_quantity);
        $this->assertEquals(0, $item2->fresh()->carryover_in_quantity);

        // Original item should restore remaining quantity
        $this->assertEquals(25, $item1->fresh()->remaining_quantity);
        $this->assertEquals(0, $item1->fresh()->carryover_out_quantity);
    }

    /**
     * @test
     * BR-SHP-005: Cannot unsettle if carryover quantity already sold
     */
    public function it_prevents_unsettle_if_carryover_quantity_was_sold(): void
    {
        // Arrange
        $supplier = Supplier::factory()->create();

        $shipment1 = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
        ]);

        $shipment2 = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $item1 = ShipmentItem::factory()->create([
            'shipment_id' => $shipment1->id,
            'remaining_quantity' => 0,
            'carryover_out_quantity' => 50,
        ]);

        $item2 = ShipmentItem::factory()->create([
            'shipment_id' => $shipment2->id,
            'product_id' => $item1->product_id,
            'initial_quantity' => 50,
            'remaining_quantity' => 10, // 40kg already sold from carryover!
            'carryover_in_quantity' => 50,
            'sold_quantity' => 40,
        ]);

        \App\Models\Carryover::create([
            'from_shipment_id' => $shipment1->id,
            'from_shipment_item_id' => $item1->id,
            'to_shipment_id' => $shipment2->id,
            'to_shipment_item_id' => $item2->id,
            'product_id' => $item1->product_id,
            'quantity' => 50,
            'cartons' => 10,
            'weight_per_unit' => 5,
            'created_by' => 1,
        ]);

        // Assert exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('الكمية المرحلة تم بيعها من الشحنة التالية');

        // Act
        $this->service->unsettle($shipment1);
    }

    /**
     * @test
     * BR-SHP-007: Cannot settle already settled shipment
     */
    public function it_prevents_settling_already_settled_shipment(): void
    {
        // Arrange
        $shipment1 = Shipment::factory()->create([
            'status' => 'settled',
        ]);

        $shipment2 = Shipment::factory()->create([
            'status' => 'open',
        ]);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('الشحنة مُصفاة بالفعل');

        // Act
        $this->service->settle($shipment1, $shipment2->id);
    }

    /**
     * @test
     * BR-SHP-004: Target shipment must be open
     */
    public function it_requires_target_shipment_to_be_open(): void
    {
        // Arrange
        $shipment1 = Shipment::factory()->create([
            'status' => 'closed',
        ]);

        $shipment2 = Shipment::factory()->create([
            'status' => 'closed', // Not open!
        ]);

        // Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('الشحنة التالية يجب أن تكون مفتوحة');

        // Act
        $this->service->settle($shipment1, $shipment2->id);
    }

    /**
     * @test
     * Settlement calculates totals correctly
     */
    public function it_calculates_settlement_totals(): void
    {
        // Arrange
        $supplier = Supplier::factory()->create(['balance' => 0]);

        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'closed',
        ]);

        // Add items with sales
        $item1 = ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 20,
            'sold_quantity' => 70,
            'wastage_quantity' => 10,
        ]);

        // Add expenses
        \App\Models\Expense::factory()->create([
            'type' => 'supplier',
            'supplier_id' => $supplier->id,
            'shipment_id' => $shipment->id,
            'amount' => 500,
        ]);

        // Act
        $nextShipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $this->service->settle($shipment, $nextShipment->id);

        // Assert
        $shipment = $shipment->fresh();

        $this->assertNotNull($shipment->settled_at);
        $this->assertNotNull($shipment->total_sales);
        $this->assertNotNull($shipment->total_wastage);
        $this->assertEquals(20, $shipment->total_carryover_out);
        $this->assertEquals(500, $shipment->total_supplier_expenses);
    }

    /**
     * @test
     * Edge Case: Settle shipment with no remaining quantity
     */
    public function it_settles_shipment_with_zero_carryover(): void
    {
        // Arrange
        $shipment1 = Shipment::factory()->create([
            'status' => 'closed',
        ]);

        $shipment2 = Shipment::factory()->create([
            'status' => 'open',
        ]);

        ShipmentItem::factory()->create([
            'shipment_id' => $shipment1->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 0, // All sold
            'sold_quantity' => 100,
        ]);

        // Act
        $this->service->settle($shipment1, $shipment2->id);

        // Assert
        $this->assertEquals('settled', $shipment1->fresh()->status);

        // No carryovers should be created
        $this->assertDatabaseMissing('carryovers', [
            'from_shipment_id' => $shipment1->id,
        ]);
    }
}
