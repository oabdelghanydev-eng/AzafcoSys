<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PDF Report Generation Tests
 *
 * 2025 Best Practices:
 * - Uses RefreshDatabase for isolation
 * - Uses Sanctum::actingAs for authentication
 * - Tests both success and error scenarios
 * - Validates PDF response headers
 */
class DailyPdfReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $testDate;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->user = User::factory()->create([
            'is_admin' => true,
            'is_locked' => false,
        ]);

        $this->testDate = now()->format('Y-m-d');

        // Create required accounts
        Account::factory()->create(['type' => 'cashbox', 'balance' => 10000]);
        Account::factory()->create(['type' => 'bank', 'balance' => 50000]);
    }

    /**
     * Test unauthenticated access returns 401
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson("/api/reports/daily/{$this->testDate}/pdf");

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_001',
                ],
            ]);
    }

    /**
     * Test daily PDF generation with empty data
     */
    public function test_daily_pdf_generation_with_empty_data(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/reports/daily/{$this->testDate}/pdf");

        // Should return PDF even with empty data
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test daily PDF generation with actual data
     */
    public function test_daily_pdf_generation_with_data(): void
    {
        Sanctum::actingAs($this->user);

        // Create minimal test data without complex relationships
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create(['balance' => 1000]);
        $product = Product::factory()->create();

        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'date' => $this->testDate,
            'status' => 'open',
        ]);

        $shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'product_id' => $product->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 80,
            'weight_per_unit' => 1.5,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => $this->testDate,
            'status' => 'active',
            'total' => 500,
        ]);

        // Create invoice item directly without factory to avoid constraints
        \DB::table('invoice_items')->insert([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'shipment_item_id' => $shipmentItem->id,
            'cartons' => 2,
            'quantity' => 20.000,
            'unit_price' => 25.00,
            'subtotal' => 500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Collection::factory()->create([
            'customer_id' => $customer->id,
            'date' => $this->testDate,
            'amount' => 300,
            'payment_method' => 'cash',
        ]);

        // Create expense directly to avoid factory constraints
        \DB::table('expenses')->insert([
            'expense_number' => 'EXP-'.rand(1000, 9999),
            'type' => 'company',
            'category' => 'other',
            'date' => $this->testDate,
            'amount' => 100.00,
            'payment_method' => 'cash',
            'description' => 'Test expense',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get("/api/reports/daily/{$this->testDate}/pdf");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition');
    }

    /**
     * Test invalid date format returns error
     */
    public function test_invalid_date_format_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/reports/daily/invalid-date/pdf');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test PDF filename includes date
     */
    public function test_pdf_filename_includes_date(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get("/api/reports/daily/{$this->testDate}/pdf");

        $response->assertStatus(200);

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString($this->testDate, $contentDisposition);
    }
}
