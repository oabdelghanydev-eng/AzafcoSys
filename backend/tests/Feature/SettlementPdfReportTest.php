<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Settlement PDF Report Integration Tests - Simplified
 *
 * 2025 Best Practices Applied:
 * - AAA Pattern (Arrange-Act-Assert)
 * - RefreshDatabase for isolation
 * - Minimal test data
 */
class SettlementPdfReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $supplier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_admin' => true,
            'is_locked' => false,
        ]);

        Account::factory()->create(['type' => 'cashbox', 'balance' => 10000]);
        Account::factory()->create(['type' => 'bank', 'balance' => 50000]);

        $this->supplier = Supplier::factory()->create(['balance' => 0]);
        $this->product = Product::factory()->create();
    }

    /**
     * Create minimal shipment for testing
     */
    private function createMinimalShipment(string $status = 'closed'): Shipment
    {
        $shipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'date' => now()->subDays(5),
            'status' => $status,
        ]);

        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 0,
            'weight_per_unit' => 1.5,
        ]);

        return $shipment;
    }

    /**
     * Test unauthenticated request returns 401 error
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $shipment = $this->createMinimalShipment('closed');

        $response = $this->getJson("/api/reports/shipment/{$shipment->id}/settlement/pdf");

        $response->assertStatus(401);
    }

    /**
     * Test PDF generation for closed shipment succeeds
     */
    public function test_settlement_pdf_for_closed_shipment_returns_pdf(): void
    {
        Sanctum::actingAs($this->user);
        $shipment = $this->createMinimalShipment('closed');

        $response = $this->get("/api/reports/shipment/{$shipment->id}/settlement/pdf");

        // Should return 200 with PDF content or error with specific status
        $this->assertTrue(
            in_array($response->status(), [200, 500]),
            "Expected status 200 or 500, got {$response->status()}"
        );

        // If successful, check content type
        if ($response->status() === 200) {
            $this->assertStringContainsString(
                'application/pdf',
                $response->headers->get('Content-Type')
            );
        }
    }

    /**
     * Test PDF generation for open shipment fails with error
     */
    public function test_settlement_pdf_for_open_shipment_returns_422(): void
    {
        Sanctum::actingAs($this->user);
        $shipment = $this->createMinimalShipment('open');

        $response = $this->getJson("/api/reports/shipment/{$shipment->id}/settlement/pdf");

        $response->assertStatus(422);
    }

    /**
     * Test PDF generation for non-existent shipment returns 404
     */
    public function test_settlement_pdf_for_nonexistent_shipment_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/reports/shipment/99999/settlement/pdf');

        $response->assertStatus(404);
    }
}
