<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Tests for Shipment Update Endpoint (Cartons-Based)
 * Updated 2025-12-19: Uses cartons for tracking
 */
class ShipmentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'permissions' => ['shipments.view', 'shipments.create', 'shipments.edit', 'shipments.delete'],
        ]);
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create();
    }

    // ============================================
    // Open Shipment Update Tests
    // ============================================

    public function test_can_update_open_shipment_date(): void
    {
        $shipment = $this->createShipment('open');

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'date' => '2025-12-20',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('2025-12-20', $shipment->fresh()->date->toDateString());
    }

    public function test_can_update_open_shipment_notes(): void
    {
        $shipment = $this->createShipment('open');

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'notes' => 'ملاحظات محدثة',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('ملاحظات محدثة', $shipment->fresh()->notes);
    }

    public function test_can_update_open_shipment_item_weight(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'weight_per_unit' => 25.5],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertEquals(25.5, (float) $item->fresh()->weight_per_unit);
    }

    // ============================================
    // Closed/Settled Shipment Update Tests
    // ============================================

    public function test_cannot_update_closed_shipment(): void
    {
        $shipment = $this->createShipment('closed');

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'notes' => 'محاولة تعديل',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SHP_009');
    }

    public function test_cannot_update_settled_shipment(): void
    {
        $shipment = $this->createShipment('settled');

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'notes' => 'محاولة تعديل',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'SHP_009');
    }

    // ============================================
    // Cartons Update Tests
    // ============================================

    public function test_can_increase_cartons(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();
        $originalCartons = $item->cartons;

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'cartons' => $originalCartons + 50],
                ],
            ]);

        $response->assertStatus(200);
        $item->refresh();
        $this->assertEquals($originalCartons + 50, $item->cartons);
    }

    public function test_cannot_reduce_cartons_below_sold(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();

        // Simulate some cartons were sold
        $item->update(['sold_cartons' => 30]);

        // Try to reduce below sold amount
        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'cartons' => 20], // Less than sold (30)
                ],
            ]);

        // Should fail - verify cartons wasn't changed
        $item->refresh();
        $this->assertEquals(100, $item->cartons);
    }

    public function test_can_reduce_cartons_to_sold_amount(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();

        // Simulate some cartons were sold
        $item->update(['sold_cartons' => 30]);

        // Reduce to exactly sold amount (remaining becomes 0)
        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'cartons' => 30],
                ],
            ]);

        $response->assertStatus(200);
        $item->refresh();
        $this->assertEquals(30, $item->cartons);
        $this->assertEquals(0, $item->remaining_cartons); // Accessor
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function createShipment(string $status): Shipment
    {
        $shipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => $status,
            'notes' => 'ملاحظات أصلية',
        ]);

        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 0,
            'weight_per_unit' => 20.0,
        ]);

        return $shipment->fresh(['items']);
    }
}

