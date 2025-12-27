<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sales By Product Report Tests
 *
 * ADR-001: Tests to prevent Quantity/Weight confusion regression.
 *
 * CRITICAL: invoice_items.quantity stores WEIGHT (kg), NOT carton count!
 * - SUM(invoice_items.cartons) = Quantity (carton count)
 * - SUM(invoice_items.quantity) = Weight (kg)
 */
class SalesByProductReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_admin' => true,
            'is_locked' => false,
        ]);
    }

    /**
     * ADR-001: Verify that Quantity = cartons, Weight = actual weight from scale
     *
     * This test prevents regression of the Quantity/Weight confusion bug.
     */
    public function test_sales_report_uses_cartons_for_quantity_and_quantity_column_for_weight(): void
    {
        Sanctum::actingAs($this->user);

        // Arrange: Create test data with KNOWN values
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name_en' => 'Test Product']);

        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'cartons' => 100,
            'sold_cartons' => 10,
            'weight_per_unit' => 25.0,  // 25 kg per carton
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'active',
        ]);

        // CRITICAL: Create invoice item with DISTINCT values for cartons vs weight
        // cartons = 10 (count)
        // quantity = 245.5 (actual weight in kg from scale - NOT 10 * 25!)
        DB::table('invoice_items')->insert([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'shipment_item_id' => $shipmentItem->id,
            'cartons' => 10,           // Number of cartons sold
            'quantity' => 245.500,     // Actual weight from scale (kg) - INTENTIONALLY DIFFERENT from 10*25
            'unit_price' => 10.00,     // Price per kg
            'subtotal' => 2455.00,     // 245.5 * 10
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Act: Call the API
        $response = $this->getJson('/api/reports/sales/by-product');

        // Assert: Response is successful
        $response->assertStatus(200)->assertJsonStructure([
            'success',
            'data' => [
                'products' => [
                    '*' => ['product_id', 'product_name', 'quantity', 'weight', 'revenue'],
                ],
                'summary' => ['total_quantity', 'total_weight', 'total_revenue'],
            ],
        ]);

        $data = $response->json('data');

        // CRITICAL ASSERTIONS - ADR-001
        // quantity MUST be 10 (cartons), NOT 245.5 (which would be wrong)
        $this->assertEquals(10, (int) $data['products'][0]['quantity'], 'Quantity should be cartons count, not weight');

        // weight MUST be 245.5 (actual weight), NOT 250 (10 * 25 which would be wrong)
        $this->assertEquals(245.5, (float) $data['products'][0]['weight'], 'Weight should be actual weight from scale');

        // Summary totals
        $this->assertEquals(10, (int) $data['summary']['total_quantity'], 'Total quantity should be sum of cartons');
        $this->assertEquals(245.5, (float) $data['summary']['total_weight'], 'Total weight should be sum of actual weights');
    }

    /**
     * Test that multiple invoice items are aggregated correctly
     */
    public function test_sales_report_aggregates_multiple_items_correctly(): void
    {
        Sanctum::actingAs($this->user);

        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['name_en' => 'Aggregation Test']);

        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'cartons' => 100,
            'sold_cartons' => 30,
            'weight_per_unit' => 20.0,
        ]);

        // Create two invoices with items
        foreach ([['cartons' => 15, 'weight' => 290.0], ['cartons' => 15, 'weight' => 305.0]] as $itemData) {
            $invoice = Invoice::factory()->create([
                'customer_id' => $customer->id,
                'status' => 'active',
            ]);

            DB::table('invoice_items')->insert([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'shipment_item_id' => $shipmentItem->id,
                'cartons' => $itemData['cartons'],
                'quantity' => $itemData['weight'],
                'unit_price' => 8.00,
                'subtotal' => $itemData['weight'] * 8.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->getJson('/api/reports/sales/by-product');
        $response->assertStatus(200);

        $data = $response->json('data');

        // 15 + 15 = 30 cartons (NOT 290 + 305 = 595!)
        $this->assertEquals(30, (int) $data['products'][0]['quantity']);

        // 290 + 305 = 595 kg
        $this->assertEquals(595.0, (float) $data['products'][0]['weight']);
    }
}
