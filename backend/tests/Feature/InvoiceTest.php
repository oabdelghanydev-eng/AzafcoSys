<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Customer;
use App\Models\User;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\Collection;
use App\Models\CollectionAllocation;

/**
 * Feature Tests for Invoice Endpoints
 * Epic 5: Sales & Invoicing
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Product $product;
    private Shipment $shipment;
    private ShipmentItem $shipmentItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'permissions' => ['invoices.view', 'invoices.create', 'invoices.cancel'],
        ]);

        $this->customer = Customer::factory()->create(['balance' => 0]);
        $this->product = Product::factory()->create();

        $supplier = Supplier::factory()->create();
        $this->shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        $this->shipmentItem = ShipmentItem::factory()->create([
            'shipment_id' => $this->shipment->id,
            'product_id' => $this->product->id,
            'initial_quantity' => 100,
            'remaining_quantity' => 100,
            'sold_quantity' => 0,
        ]);
    }

    /**
     * Helper to make invoice requests without working day middleware
     */
    private function invoiceRequest(string $method, string $uri, array $data = [])
    {
        return $this->actingAs($this->user)
                    ->withoutMiddleware(\App\Http\Middleware\EnsureWorkingDay::class)
            ->{$method}($uri, $data);
    }

    // ============================================
    // Invoice Creation Tests
    // ============================================

    public function test_can_create_invoice_with_items(): void
    {
        $response = $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'status' => 'active',
        ]);
    }

    public function test_invoice_updates_customer_balance(): void
    {
        $initialBalance = $this->customer->balance;

        $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $this->customer->refresh();
        $expectedBalance = (float) $initialBalance + 500;
        $actualBalance = (float) $this->customer->balance;
        $this->assertEqualsWithDelta($expectedBalance, $actualBalance, 0.01, "Balance should be {$expectedBalance}, got {$actualBalance}");
    }

    public function test_invoice_uses_fifo_allocation(): void
    {
        $initialRemaining = $this->shipmentItem->remaining_quantity;

        $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 30,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $this->shipmentItem->refresh();
        $this->assertEquals($initialRemaining - 30, (float) $this->shipmentItem->remaining_quantity);
        $this->assertEquals(30, (float) $this->shipmentItem->sold_quantity);
    }

    // ============================================
    // Invoice Deletion Prevention
    // ============================================

    public function test_cannot_delete_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        // Trying to delete should throw exception
        $this->expectException(\App\Exceptions\BusinessException::class);
        $invoice->delete();
    }

    // ============================================
    // Invoice Cancellation Tests
    // ============================================

    public function test_can_cancel_unpaid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'date' => now()->toDateString(), // Within edit window
            'total' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'active',
        ]);

        $response = $this->invoiceRequest('postJson', "/api/invoices/{$invoice->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $invoice->fresh()->status);
    }

    public function test_cannot_cancel_paid_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'date' => now()->toDateString(), // Within edit window
            'total' => 500,
            'balance' => 200,
            'paid_amount' => 300,
            'status' => 'active',
        ]);

        $response = $this->invoiceRequest('postJson', "/api/invoices/{$invoice->id}/cancel");

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'INV_008');
    }

    public function test_cancel_restores_fifo_quantities(): void
    {
        // Create invoice that uses FIFO
        $response = $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $invoiceId = $response->json('data.id');
        $this->shipmentItem->refresh();
        $remainingAfterSale = $this->shipmentItem->remaining_quantity;

        // Cancel the invoice
        $this->invoiceRequest('postJson', "/api/invoices/{$invoiceId}/cancel");

        $this->shipmentItem->refresh();
        $this->assertEquals($remainingAfterSale + 20, (float) $this->shipmentItem->remaining_quantity);
    }

    // ============================================
    // Wastage Invoice Tests
    // ============================================

    public function test_wastage_invoice_does_not_affect_customer_balance(): void
    {
        $initialBalance = $this->customer->balance;

        $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'type' => 'wastage',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $this->customer->refresh();
        $this->assertEquals($initialBalance, (float) $this->customer->balance);
    }

    public function test_wastage_invoice_still_allocates_fifo(): void
    {
        $initialRemaining = $this->shipmentItem->remaining_quantity;

        $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'type' => 'wastage',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'unit_price' => 50,
                ],
            ],
        ]);

        $this->shipmentItem->refresh();
        $this->assertEquals($initialRemaining - 15, (float) $this->shipmentItem->remaining_quantity);
    }

    // ============================================
    // Discount Validation Tests
    // ============================================

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $response = $this->invoiceRequest('postJson', '/api/invoices', [
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'discount' => 1000,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 50, // subtotal = 500
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('discount');
    }
}
