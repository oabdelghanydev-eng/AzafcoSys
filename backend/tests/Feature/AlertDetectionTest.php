<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\Product;
use App\Models\AiAlert;
use App\Services\AlertDetectionService;

/**
 * Feature Tests for Alert Detection
 * Epic 8: AI Alerts & Smart Rules
 */
class AlertDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AlertDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['alerts.view', 'alerts.create', 'alerts.resolve'],
        ]);

        $this->service = app(AlertDetectionService::class);
    }

    /**
     * Helper to bypass middleware
     */
    private function alertRequest(string $method, string $uri, array $data = [])
    {
        return $this->actingAs($this->user)
                    ->withoutMiddleware(\App\Http\Middleware\EnsureWorkingDay::class)
            ->{$method}($uri, $data);
    }

    // ============================================
    // Price Anomaly Detection Tests
    // ============================================

    public function test_price_anomaly_detected_when_deviation_exceeds_threshold(): void
    {
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        // Create historical invoices with average price of 100
        for ($i = 0; $i < 5; $i++) {
            $invoice = Invoice::factory()->create([
                'customer_id' => $customer->id,
                'date' => now()->subDays($i + 1),
            ]);
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'unit_price' => 100,
                'quantity' => 10,
                'created_at' => now()->subDays($i + 1),
            ]);
        }

        // Create today's invoice with anomalous price (150 = 50% deviation)
        $todayInvoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now(),
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $todayInvoice->id,
            'product_id' => $product->id,
            'unit_price' => 150,
            'quantity' => 10,
            'created_at' => now(),
        ]);

        // Run detection
        $alerts = $this->service->detectPriceAnomalies(0.3);

        // Assert alert created
        $this->assertNotEmpty($alerts);
        $this->assertEquals('price_anomaly', $alerts[0]->type);
    }

    // ============================================
    // Shipment Delay Detection Tests
    // ============================================

    public function test_shipment_delay_detected_when_open_too_long(): void
    {
        // Create old open shipment (20 days ago, threshold is 14)
        $shipment = Shipment::factory()->create([
            'status' => 'open',
            'date' => now()->subDays(20),
        ]);

        // Run detection
        $alerts = $this->service->detectShipmentDelays();

        // Assert alert created
        $this->assertNotEmpty($alerts);
        $this->assertEquals('shipment_delay', $alerts[0]->type);
        $this->assertArrayHasKey('open_days', $alerts[0]->data);
    }

    public function test_no_delay_alert_for_recent_shipment(): void
    {
        // Create recent open shipment (5 days ago)
        Shipment::factory()->create([
            'status' => 'open',
            'date' => now()->subDays(5),
        ]);

        // Run detection
        $alerts = $this->service->detectShipmentDelays();

        // No alerts should be created
        $this->assertEmpty($alerts);
    }

    // ============================================
    // Overdue Customer Detection Tests
    // ============================================

    public function test_overdue_customer_detected_when_invoice_unpaid_too_long(): void
    {
        $customer = Customer::factory()->create(['balance' => 1000]);

        // Create old unpaid invoice (45 days ago, threshold is 30)
        Invoice::factory()->create([
            'customer_id' => $customer->id,
            'date' => now()->subDays(45),
            'balance' => 1000,
            'status' => 'active',
        ]);

        // Run detection
        $alerts = $this->service->detectOverdueCustomers();

        // Assert alert created
        $this->assertNotEmpty($alerts);
        $this->assertEquals('overdue_customer', $alerts[0]->type);
    }

    // ============================================
    // API Endpoint Tests
    // ============================================

    public function test_can_list_alerts(): void
    {
        AiAlert::factory()->count(3)->create();

        $response = $this->alertRequest('getJson', '/api/alerts');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_get_alert_summary(): void
    {
        AiAlert::factory()->count(5)->create(['is_resolved' => false]);
        AiAlert::factory()->count(2)->create(['is_resolved' => true]);

        $response = $this->alertRequest('getJson', '/api/alerts/summary');

        $response->assertStatus(200);
        $response->assertJsonPath('data.unresolved_alerts', 5);
    }

    public function test_can_resolve_alert(): void
    {
        $alert = AiAlert::factory()->create(['is_resolved' => false]);

        $response = $this->alertRequest('postJson', "/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(200);
        $this->assertTrue($alert->fresh()->is_resolved);
    }
}
