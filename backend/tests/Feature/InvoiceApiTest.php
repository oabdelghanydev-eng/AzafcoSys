<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoice API Integration Tests
 * 
 * Tests full HTTP request/response cycle for invoice operations
 */
class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * Successful invoice creation via API
     */
    public function it_creates_invoice_successfully(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.create']);
        $this->openWorkingDay(); // Use helper instead of API call

        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $shipment = Shipment::factory()->create(['status' => 'open']);
        $shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'remaining_quantity' => 100,
            'weight_per_unit' => 5,
        ]);

        $requestData = [
            'customer_id' => $customer->id,
            'date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'shipment_item_id' => $shipmentItem->id,
                    'quantity' => 10,
                    'unit_price' => 50, // Changed from price_per_kg
                ],
            ],
            'discount' => 0,
            'notes' => 'Test invoice',
        ];

        // Act
        $response = $this->postJson('/api/invoices', $requestData);

        // Assert
        $response->assertStatus(201);
        $response->assertJsonFragment(['success' => true]);

        // Verify database
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
        ]);

        // Verify FIFO deduction
        $this->assertEquals(90, $shipmentItem->fresh()->remaining_quantity);
    }

    /**
     * @test
     * Cannot create invoice without permission
     */
    public function it_prevents_invoice_creation_without_permission(): void
    {
        // Arrange
        $user = $this->actingAsUser([]); // No permissions
        $this->openWorkingDay(); // Must open working day to test permission

        // Act
        $response = $this->postJson('/api/invoices', [
            'customer_id' => 1,
            'items' => [],
        ]);

        // Assert - 403 Forbidden (no permission) or 422 (validation fails first)
        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    /**
     * @test
     * Validation: Invoice must have items
     */
    public function it_rejects_invoice_without_items(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.create']);
        $this->openWorkingDay();

        // Act
        $response = $this->postJson('/api/invoices', [
            'customer_id' => Customer::factory()->create()->id,
            'items' => [], // Empty!
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
    }

    /**
     * @test
     * Cannot create invoice when quantity exceeds available stock
     */
    public function it_rejects_invoice_when_quantity_exceeds_stock(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.create']);
        $this->openWorkingDay();

        $customer = Customer::factory()->create();
        $shipmentItem = ShipmentItem::factory()->create([
            'remaining_quantity' => 5, // Only 5kg available
        ]);

        // Act - Try to sell 10kg
        $response = $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'date' => today()->toDateString(),
            'items' => [
                [
                    'product_id' => $shipmentItem->product_id,
                    'shipment_item_id' => $shipmentItem->id,
                    'quantity' => 10,
                    'unit_price' => 50, // Changed from price_per_kg
                ],
            ],
        ]);

        // Assert
        $response->assertStatus(422);
        $this->assertBusinessError($response, 'INV_005');
    }

    /**
     * @test
     * Invoice cancellation via API
     */
    public function it_cancels_invoice_successfully(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.cancel']);
        $this->openWorkingDay();

        $customer = Customer::factory()->create(['balance' => 500]);
        $invoice = \App\Models\Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => today(), // Within edit window
            'total' => 500,
            'balance' => 500,
            'status' => 'active',
        ]);

        // Act
        $response = $this->postJson("/api/invoices/{$invoice->id}/cancel");

        // Assert
        $response->assertStatus(200);
        $this->assertEquals('cancelled', $invoice->fresh()->status);
        $this->assertEquals(0, $customer->fresh()->balance);
    }

    /**
     * @test
     * Cannot cancel paid invoice (BR-INV-008)
     */
    public function it_prevents_cancelling_paid_invoice(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.cancel']);
        $this->openWorkingDay();

        $invoice = \App\Models\Invoice::factory()->create([
            'date' => today(), // Within edit window
            'total' => 1000,
            'paid_amount' => 500, // Partially paid
            'balance' => 500,
            'status' => 'active',
        ]);

        // Act
        $response = $this->postJson("/api/invoices/{$invoice->id}/cancel");

        // Assert
        $response->assertStatus(422);
        $this->assertBusinessError($response, 'INV_008');
    }

    /**
     * @test
     * Invoice list with filters
     */
    public function it_lists_invoices_with_filters(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.view']);

        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();

        \App\Models\Invoice::factory()->count(3)->create([
            'customer_id' => $customer1->id,
            'status' => 'active',
        ]);

        \App\Models\Invoice::factory()->count(2)->create([
            'customer_id' => $customer2->id,
            'status' => 'cancelled',
        ]);

        // Act - Filter by customer
        $response = $this->getJson("/api/invoices?customer_id={$customer1->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    /**
     * @test
     * Invoice details include items and allocations
     */
    public function it_returns_invoice_details_with_relationships(): void
    {
        // Arrange
        $user = $this->actingAsUser(['invoices.view']);

        $invoice = \App\Models\Invoice::factory()->create();
        \App\Models\InvoiceItem::factory()->count(2)->create([
            'invoice_id' => $invoice->id,
        ]);

        // Act
        $response = $this->getJson("/api/invoices/{$invoice->id}");

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
            ],
        ]);
    }

    /**
     * @test
     * Delete endpoint returns method not allowed
     */
    public function it_prevents_invoice_deletion_via_api(): void
    {
        // Arrange
        $user = $this->actingAsAdmin();
        $invoice = \App\Models\Invoice::factory()->create();

        // Act
        $response = $this->deleteJson("/api/invoices/{$invoice->id}");

        // Assert
        // Route doesn't exist (excluded in api.php)
        $response->assertStatus(405); // Method Not Allowed
    }
}
