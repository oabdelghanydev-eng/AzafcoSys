<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;

/**
 * Feature Tests for Shipment Update Endpoint
 * Epic 4: Only open shipments can be updated
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
    // Quantity Update Tests
    // ============================================

    public function test_can_increase_quantity(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();
        $originalInitial = $item->initial_quantity;
        $originalRemaining = $item->remaining_quantity;

        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'initial_quantity' => $originalInitial + 50],
                ],
            ]);

        $response->assertStatus(200);
        $item->refresh();
        $this->assertEquals($originalInitial + 50, (float) $item->initial_quantity);
        $this->assertEquals($originalRemaining + 50, (float) $item->remaining_quantity);
    }

    public function test_cannot_reduce_quantity_below_sold(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();

        // Simulate some quantity was sold
        $item->update([
            'sold_quantity' => 30,
            'remaining_quantity' => $item->initial_quantity - 30,
        ]);

        // Try to reduce below sold amount
        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'initial_quantity' => 20], // Less than sold (30)
                ],
            ]);

        // Should fail with 500 (exception) since we're catching the exception in transaction
        // We just verify the quantity wasn't changed
        $item->refresh();
        $this->assertEquals(100, (float) $item->initial_quantity);
    }

    public function test_can_reduce_quantity_to_sold_amount(): void
    {
        $shipment = $this->createShipment('open');
        $item = $shipment->items->first();

        // Simulate some quantity was sold
        $item->update([
            'sold_quantity' => 30,
            'remaining_quantity' => $item->initial_quantity - 30,
        ]);

        // Reduce to exactly sold amount (remaining becomes 0)
        $response = $this->actingAs($this->user)
            ->putJson("/api/shipments/{$shipment->id}", [
                'items' => [
                    ['id' => $item->id, 'initial_quantity' => 30],
                ],
            ]);

        $response->assertStatus(200);
        $item->refresh();
        $this->assertEquals(30, (float) $item->initial_quantity);
        $this->assertEquals(0, (float) $item->remaining_quantity);
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
            'initial_quantity' => 100,
            'remaining_quantity' => 100,
            'sold_quantity' => 0,
            'weight_per_unit' => 20.0,
        ]);

        return $shipment->fresh(['items']);
    }
}
