<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Carryover;
use App\Services\Reports\ShipmentSettlementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Settlement Report Accuracy Tests
 * 
 * Verifies that the settlement report calculates all values correctly
 */
class SettlementReportAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Customer $customer;
    private Product $product;
    private ShipmentSettlementReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->supplier = Supplier::factory()->create([
            'opening_balance' => 10000,
        ]);
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create();
        $this->reportService = app(ShipmentSettlementReportService::class);

        $this->actingAs($this->admin);
    }

    /**
     * @test
     * Incoming items calculation
     */
    public function it_calculates_incoming_items_correctly(): void
    {
        // Arrange
        $shipment = $this->createSettledShipment();

        // 3 items: 100, 150, 50 cartons = 300 total
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 100,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        $product2 = Product::factory()->create();
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $product2->id,
            'cartons' => 150,
            'sold_cartons' => 150,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 3.0,
        ]);

        $product3 = Product::factory()->create();
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $product3->id,
            'cartons' => 50,
            'sold_cartons' => 50,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.0,
        ]);

        // Act
        $report = $this->reportService->generate($shipment);

        // Assert
        $this->assertEquals(3, $report['incomingItems']->count());
        $this->assertEquals(300, $report['totalIncomingCartons']);
    }

    /**
     * @test
     * Sales total from invoice items
     */
    public function it_calculates_sales_from_invoice_items(): void
    {
        // Arrange
        $shipment = $this->createSettledShipment();
        $shipmentItem = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 80,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 20,
            'weight_per_unit' => 2.5,
        ]);

        // Create invoice with items
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'total' => 20000,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'shipment_item_id' => $shipmentItem->id,
            'cartons' => 80,
            'quantity' => 200, // 80 × 2.5 kg
            'unit_price' => 100,
            'subtotal' => 20000, // 200 kg × 100
        ]);

        // Act
        $report = $this->reportService->generate($shipment);

        // Assert
        $this->assertEquals(20000, $report['totalSalesAmount']);
    }

    /**
     * @test
     * Previous balance uses opening_balance for first shipment
     */
    public function it_uses_opening_balance_for_first_shipment(): void
    {
        // Arrange
        $shipment = $this->createSettledShipment();
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 100,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Act
        $report = $this->reportService->generate($shipment);

        // Assert: Previous balance should be supplier's opening_balance
        $this->assertEquals(10000, $report['previousBalance']);
    }

    /**
     * @test
     * Previous balance chains from previous shipment
     */
    public function it_chains_previous_balance_from_settled_shipment(): void
    {
        // Arrange: First settled shipment
        $shipment1 = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'settled',
            'final_supplier_balance' => 25000, // Stored final balance
        ]);

        // Second shipment
        $shipment2 = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'settled',
        ]);
        ShipmentItem::create([
            'shipment_id' => $shipment2->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 100,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Act
        $report = $this->reportService->generate($shipment2);

        // Assert: Previous balance = first shipment's final balance
        $this->assertEquals(25000, $report['previousBalance']);
    }

    /**
     * @test
     * Carryover out items are calculated correctly
     */
    public function it_calculates_carryover_out_correctly(): void
    {
        // Arrange
        $shipment = $this->createSettledShipment();
        $shipmentItem = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 70,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 30,
            'weight_per_unit' => 2.5,
        ]);

        // Create carryover record
        $nextShipment = Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'open',
        ]);

        // Create target shipment item for carryover
        $targetItem = ShipmentItem::create([
            'shipment_id' => $nextShipment->id,
            'product_id' => $this->product->id,
            'cartons' => 0,
            'sold_cartons' => 0,
            'carryover_in_cartons' => 30,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        Carryover::create([
            'from_shipment_id' => $shipment->id,
            'from_shipment_item_id' => $shipmentItem->id,
            'to_shipment_id' => $nextShipment->id,
            'to_shipment_item_id' => $targetItem->id,
            'product_id' => $this->product->id,
            'cartons' => 30,
            'reason' => 'end_of_shipment',
            'created_by' => $this->admin->id,
        ]);

        // Act
        $report = $this->reportService->generate($shipment);

        // Assert
        $this->assertEquals(1, $report['carryoverOut']->count());
        $this->assertEquals(30, $report['carryoverOut']->first()->cartons);
    }

    /**
     * @test
     * Final balance calculation is correct
     */
    public function it_calculates_final_balance_correctly(): void
    {
        // Arrange
        $shipment = $this->createSettledShipment();
        $shipmentItem = ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 100,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Create invoice: 100 cartons × 2.5 kg × 100 = 25,000
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'active',
            'total' => 25000,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'shipment_item_id' => $shipmentItem->id,
            'cartons' => 100,
            'quantity' => 250,
            'unit_price' => 100,
            'subtotal' => 25000,
        ]);

        // Create expense linked to shipment
        Expense::factory()->create([
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'shipment_id' => $shipment->id,
            'amount' => 500,
        ]);

        // Act
        $report = $this->reportService->generate($shipment);

        // Assert
        // Formula: Sales (25,000) - Commission (1,500) - Expenses (500) + Previous (10,000) = 33,000
        $expectedFinal = 25000 - (25000 * 0.06) - 500 + 10000;
        $this->assertEquals($expectedFinal, $report['finalSupplierBalance']);
    }

    /**
     * @test
     * Expenses linked to shipment only
     */
    public function it_only_counts_expenses_linked_to_shipment(): void
    {
        // Arrange
        $shipment = $this->createSettledShipment();
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->product->id,
            'cartons' => 100,
            'sold_cartons' => 100,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
            'weight_per_unit' => 2.5,
        ]);

        // Linked expense
        Expense::factory()->create([
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'shipment_id' => $shipment->id,
            'amount' => 500,
        ]);

        // Unlinked expense (should NOT be counted)
        Expense::factory()->create([
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'shipment_id' => null, // Not linked
            'amount' => 1000,
        ]);

        // Act
        $report = $this->reportService->generate($shipment);

        // Assert: Only 500 counted, not 1500
        $this->assertEquals(500, $report['totalSupplierExpenses']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════

    private function createSettledShipment(): Shipment
    {
        return Shipment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'settled',
            'created_by' => $this->admin->id,
        ]);
    }
}
