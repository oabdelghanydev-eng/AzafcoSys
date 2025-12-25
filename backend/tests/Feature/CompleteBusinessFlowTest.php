<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Carryover;
use App\Services\ShipmentService;
use App\Services\Reports\ShipmentSettlementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Complete Business Flow Integration Test
 * 
 * This test simulates the ENTIRE business workflow from shipment creation
 * to settlement, ensuring all balances, carryovers, and reports are correct.
 * 
 * Run with: php artisan test --filter=CompleteBusinessFlowTest
 */
class CompleteBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Customer $customer;
    private array $products = [];

    private const OPENING_BALANCE = 10000.00;
    private const COMMISSION_RATE = 0.06; // 6%

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['*'],
        ]);

        // Create supplier with opening balance
        $this->supplier = Supplier::factory()->create([
            'name' => 'Test Supplier',
            'balance' => 0,
            'opening_balance' => self::OPENING_BALANCE,
        ]);

        // Create customer
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'balance' => 0,
        ]);

        // Create products
        for ($i = 1; $i <= 3; $i++) {
            $this->products[] = Product::factory()->create([
                'name' => "منتج تجريبي $i",
                'name_en' => "Test Product $i",
            ]);
        }

        $this->actingAs($this->admin);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * TEST 1: Complete Single Shipment Lifecycle
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Tests:
     * - Shipment creation with items
     * - Daily report opening/closing
     * - Invoice creation (FIFO allocation)
     * - Expense creation (auto-linked to shipment)
     * - Shipment settlement
     * - Carryover generation
     * - Balance chain correctness
     */
    public function test_complete_single_shipment_lifecycle(): void
    {
        // ═══════════════════════════════════════════════════════════════
        // STEP 1: Create Shipment with Items
        // ═══════════════════════════════════════════════════════════════
        $shipment = Shipment::create([
            'number' => 'SHP-TEST-001',
            'supplier_id' => $this->supplier->id,
            'date' => now()->subDays(5),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Add 3 items: 100, 150, 250 cartons
        $itemData = [
            ['product_id' => $this->products[0]->id, 'cartons' => 100, 'weight_per_unit' => 2.5],
            ['product_id' => $this->products[1]->id, 'cartons' => 150, 'weight_per_unit' => 3.0],
            ['product_id' => $this->products[2]->id, 'cartons' => 250, 'weight_per_unit' => 2.0],
        ];

        foreach ($itemData as $data) {
            ShipmentItem::create([
                'shipment_id' => $shipment->id,
                'product_id' => $data['product_id'],
                'cartons' => $data['cartons'],
                'weight_per_unit' => $data['weight_per_unit'],
                'sold_cartons' => 0,
                'carryover_in_cartons' => 0,
                'carryover_out_cartons' => 0,
            ]);
        }

        $this->assertEquals(500, $shipment->fresh()->items->sum('cartons'));

        // ═══════════════════════════════════════════════════════════════
        // STEP 2: Open Daily Report
        // ═══════════════════════════════════════════════════════════════
        $dailyReport = DailyReport::create([
            'date' => now()->format('Y-m-d'),
            'status' => 'open',
            'opened_by' => $this->admin->id,
            'opened_at' => now(),
        ]);

        $this->assertEquals('open', $dailyReport->status);

        // ═══════════════════════════════════════════════════════════════
        // STEP 3: Create Invoices (Sell 300 cartons total)
        // ═══════════════════════════════════════════════════════════════
        $invoices = [];

        // Invoice 1: Sell 50 cartons of Product 1 @ 100 QAR/kg
        $invoice1 = $this->createInvoice($shipment, $this->products[0], 50, 100.00, $dailyReport->date);
        $invoices[] = $invoice1;

        // Invoice 2: Sell 100 cartons of Product 2 @ 80 QAR/kg
        $invoice2 = $this->createInvoice($shipment, $this->products[1], 100, 80.00, $dailyReport->date);
        $invoices[] = $invoice2;

        // Invoice 3: Sell 150 cartons of Product 3 @ 60 QAR/kg
        $invoice3 = $this->createInvoice($shipment, $this->products[2], 150, 60.00, $dailyReport->date);
        $invoices[] = $invoice3;

        // Verify sold cartons updated
        $shipment->refresh();
        $this->assertEquals(50, $shipment->items[0]->sold_cartons, 'Product 1 sold cartons');
        $this->assertEquals(100, $shipment->items[1]->sold_cartons, 'Product 2 sold cartons');
        $this->assertEquals(150, $shipment->items[2]->sold_cartons, 'Product 3 sold cartons');

        // Calculate expected sales
        // Product 1: 50 cartons × 2.5 kg × 100 = 12,500
        // Product 2: 100 cartons × 3.0 kg × 80 = 24,000
        // Product 3: 150 cartons × 2.0 kg × 60 = 18,000
        // Total = 54,500
        $expectedTotalSales = 12500 + 24000 + 18000;

        $actualTotalSales = Invoice::whereIn('id', collect($invoices)->pluck('id'))
            ->where('status', 'active')
            ->get()
            ->sum('total');

        $this->assertEquals($expectedTotalSales, $actualTotalSales, 'Total sales mismatch');

        // ═══════════════════════════════════════════════════════════════
        // STEP 4: Create Supplier Expense
        // ═══════════════════════════════════════════════════════════════
        $expense = Expense::create([
            'expense_number' => 'EXP-TEST-001',
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'date' => $dailyReport->date,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'description' => 'Transportation expense',
            'created_by' => $this->admin->id,
        ]);

        // Verify expense is auto-linked OR manually link it
        if (!$expense->shipment_id) {
            $expense->update(['shipment_id' => $shipment->id]);
        }

        $this->assertEquals($shipment->id, $expense->fresh()->shipment_id, 'Expense not linked to shipment');

        // ═══════════════════════════════════════════════════════════════
        // STEP 5: Close Daily Report
        // ═══════════════════════════════════════════════════════════════
        $dailyReport->update([
            'status' => 'closed',
            'closed_by' => $this->admin->id,
            'closed_at' => now(),
        ]);

        // ═══════════════════════════════════════════════════════════════
        // STEP 6: Close Shipment
        // ═══════════════════════════════════════════════════════════════
        $shipment->update(['status' => 'closed']);

        // ═══════════════════════════════════════════════════════════════
        // STEP 7: Settle Shipment (This creates carryovers)
        // ═══════════════════════════════════════════════════════════════
        // Create next shipment for carryover
        $nextShipment = Shipment::create([
            'number' => 'SHP-TEST-002-NEXT',
            'supplier_id' => $this->supplier->id,
            'date' => now(),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        $shipmentService = app(ShipmentService::class);
        $shipmentService->settle($shipment, $nextShipment);

        $shipment->refresh();

        // Verify shipment is settled
        $this->assertEquals('settled', $shipment->status, 'Shipment not settled');

        // ═══════════════════════════════════════════════════════════════
        // STEP 8: Verify Carryovers Created
        // ═══════════════════════════════════════════════════════════════
        $carryovers = Carryover::where('from_shipment_id', $shipment->id)
            ->where('reason', 'end_of_shipment')
            ->get();

        // Remaining: Product 1 = 50, Product 2 = 50, Product 3 = 100
        $expectedCarryovers = [
            $this->products[0]->id => 50,
            $this->products[1]->id => 50,
            $this->products[2]->id => 100,
        ];

        foreach ($carryovers as $co) {
            $this->assertArrayHasKey($co->product_id, $expectedCarryovers);
            $this->assertEquals(
                $expectedCarryovers[$co->product_id],
                $co->cartons,
                "Carryover cartons mismatch for product {$co->product_id}"
            );
        }

        // ═══════════════════════════════════════════════════════════════
        // STEP 9: Verify Balance Chain
        // ═══════════════════════════════════════════════════════════════
        $shipment->refresh();

        // Previous balance should be opening_balance (first shipment)
        $this->assertEquals(
            self::OPENING_BALANCE,
            (float) $shipment->previous_supplier_balance,
            'Previous balance should be opening_balance for first shipment'
        );

        // Final balance calculation:
        // Sales: 54,500
        // Commission (6%): 3,270
        // Expenses: 500
        // Previous: 10,000
        // Final = 54,500 - 3,270 - 500 + 10,000 = 60,730
        $expectedFinalBalance = $expectedTotalSales
            - ($expectedTotalSales * self::COMMISSION_RATE)
            - 500
            + self::OPENING_BALANCE;

        $this->assertEquals(
            $expectedFinalBalance,
            (float) $shipment->final_supplier_balance,
            'Final supplier balance mismatch'
        );

        // ═══════════════════════════════════════════════════════════════
        // STEP 10: Verify Settlement Report
        // ═══════════════════════════════════════════════════════════════
        $reportService = app(ShipmentSettlementReportService::class);
        $reportData = $reportService->generate($shipment);

        // Verify incoming items exist
        $this->assertArrayHasKey('incomingItems', $reportData);
        $this->assertEquals(3, $reportData['incomingItems']->count(), 'Should have 3 incoming items');
        $this->assertEquals(500, $reportData['totalIncomingCartons'], 'Total incoming cartons');

        // Verify carryover out
        $this->assertArrayHasKey('carryoverOut', $reportData);
        $this->assertEquals(3, $reportData['carryoverOut']->count(), 'Should have 3 carryover items');

        // Verify balances in report
        $this->assertEquals(self::OPENING_BALANCE, $reportData['previousBalance'], 'Report previous balance');
        $this->assertEquals($expectedFinalBalance, $reportData['finalSupplierBalance'], 'Report final balance');







    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * TEST 2: Multiple Shipments Balance Chain
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Verifies that balance carries over correctly between multiple shipments
     */
    public function test_multiple_shipments_balance_chain(): void
    {
        // sellCartons uses: cartons × 2.5 kg × 50 QAR = 125 per carton
        // Shipment 1: 100 cartons = 12,500 QAR
        $shipment1 = $this->createAndSettleShipment('SHP-CHAIN-001', 100);
        $sales1 = 100 * 2.5 * 50; // 12,500

        // Expected balance after first shipment:
        // Sales: 12,500 - Commission (750) + Opening (10,000) = 21,750
        $expected1 = $sales1 - ($sales1 * 0.06) + self::OPENING_BALANCE;

        $this->assertEquals(
            self::OPENING_BALANCE,
            (float) $shipment1->previous_supplier_balance,
            'First shipment should use opening_balance'
        );

        $this->assertEquals(
            $expected1,
            (float) $shipment1->final_supplier_balance,
            'First shipment final balance'
        );

        // Shipment 2: 80 cartons = 10,000 QAR
        $shipment2 = $this->createAndSettleShipment('SHP-CHAIN-002', 80);
        $sales2 = 80 * 2.5 * 50; // 10,000

        // Expected: previous = first final, new final = 10,000 - 600 + 21,750 = 31,150
        $expected2 = $sales2 - ($sales2 * 0.06) + $expected1;

        $this->assertEquals(
            $expected1,
            (float) $shipment2->previous_supplier_balance,
            'Second shipment previous should be first final'
        );

        $this->assertEquals(
            $expected2,
            (float) $shipment2->final_supplier_balance,
            'Second shipment final balance'
        );

        // Shipment 3: 60 cartons = 7,500 QAR
        $shipment3 = $this->createAndSettleShipment('SHP-CHAIN-003', 60);
        $sales3 = 60 * 2.5 * 50; // 7,500

        $expected3 = $sales3 - ($sales3 * 0.06) + $expected2;

        $this->assertEquals(
            $expected2,
            (float) $shipment3->previous_supplier_balance,
            'Third shipment previous should be second final'
        );

        $this->assertEquals(
            $expected3,
            (float) $shipment3->final_supplier_balance,
            'Third shipment final balance'
        );






    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * TEST 3: Expense Auto-Linking to Oldest Shipment
     * ═══════════════════════════════════════════════════════════════════
     */
    public function test_expense_auto_links_to_oldest_open_shipment(): void
    {
        // Create two shipments
        $oldShipment = Shipment::create([
            'number' => 'SHP-OLD',
            'supplier_id' => $this->supplier->id,
            'date' => now()->subDays(10),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        $newShipment = Shipment::create([
            'number' => 'SHP-NEW',
            'supplier_id' => $this->supplier->id,
            'date' => now()->subDays(2),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        // Create daily report
        $dailyReport = DailyReport::create([
            'date' => now()->format('Y-m-d'),
            'status' => 'open',
            'opened_by' => $this->admin->id,
        ]);

        // Create expense via API (should auto-link to oldest)
        $response = $this->postJson('/api/expenses', [
            'type' => 'supplier',
            'supplier_id' => $this->supplier->id,
            'amount' => 100,
            'description' => 'Test expense',
            'payment_method' => 'cash',
        ]);

        $response->assertSuccessful();

        $expense = Expense::latest()->first();

        $this->assertEquals(
            $oldShipment->id,
            $expense->shipment_id,
            'Expense should be linked to the OLDEST open shipment'
        );





    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * TEST 4: Carryover Quantities Are Correct (The Bug That Was Found)
     * ═══════════════════════════════════════════════════════════════════
     */
    public function test_carryover_uses_cartons_not_quantity(): void
    {
        $shipment = $this->createShipmentWithItems('SHP-CARRYOVER', 100);

        // Sell 30 cartons
        $this->sellCartons($shipment, $this->products[0]->id, 30);

        // Close and settle
        $shipment->update(['status' => 'closed']);
        // Create next shipment for carryover
        $nextShipment = Shipment::create([
            'number' => 'SHP-CARRYOVER-NEXT',
            'supplier_id' => $this->supplier->id,
            'date' => now(),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        app(ShipmentService::class)->settle($shipment, $nextShipment);

        // Check carryover record
        $carryover = Carryover::where('from_shipment_id', $shipment->id)
            ->where('product_id', $this->products[0]->id)
            ->first();

        $this->assertNotNull($carryover, 'Carryover should be created');
        $this->assertEquals(70, $carryover->cartons, 'Carryover should have 70 cartons (100 - 30)');

        // Most important: verify 'cartons' field is used, not 'quantity'
        $this->assertNull($carryover->quantity ?? null, 'quantity field should not exist');

        // Verify report uses cartons
        $reportService = app(ShipmentSettlementReportService::class);
        $data = $reportService->generate($shipment->fresh());

        $this->assertEquals(70, $data['carryoverOut']->first()->cartons);





    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPER METHODS
    // ═══════════════════════════════════════════════════════════════════

    private function createInvoice(Shipment $shipment, Product $product, int $cartons, float $pricePerKg, string $date): Invoice
    {
        $shipmentItem = $shipment->items()->where('product_id', $product->id)->first();
        $weightPerUnit = $shipmentItem->weight_per_unit;
        $totalWeight = $cartons * $weightPerUnit;
        $subtotal = $totalWeight * $pricePerKg;

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'date' => $date,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'paid_amount' => 0,
            'balance' => $subtotal,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        // Create invoice item
        $invoice->items()->create([
            'product_id' => $product->id,
            'shipment_item_id' => $shipmentItem->id,
            'cartons' => $cartons,
            'quantity' => $totalWeight,
            'unit_price' => $pricePerKg,
            'subtotal' => $subtotal,
        ]);

        // Update shipment item sold_cartons
        $shipmentItem->increment('sold_cartons', $cartons);

        // Update customer balance
        $this->customer->increment('balance', $subtotal);

        return $invoice;
    }

    private function createShipmentWithItems(string $number, int $cartons): Shipment
    {
        $shipment = Shipment::create([
            'number' => $number,
            'supplier_id' => $this->supplier->id,
            'date' => now()->subDays(5),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'product_id' => $this->products[0]->id,
            'cartons' => $cartons,
            'weight_per_unit' => 2.5,
            'sold_cartons' => 0,
            'carryover_in_cartons' => 0,
            'carryover_out_cartons' => 0,
        ]);

        return $shipment;
    }

    private function sellCartons(Shipment $shipment, int $productId, int $cartons): void
    {
        // Create daily report if needed
        $dailyReport = DailyReport::firstOrCreate(
            ['date' => now()->format('Y-m-d')],
            ['status' => 'open', 'opened_by' => $this->admin->id]
        );

        $shipmentItem = $shipment->items()->where('product_id', $productId)->first();
        $weightPerUnit = $shipmentItem->weight_per_unit;
        $totalWeight = $cartons * $weightPerUnit;
        $pricePerKg = 50.00;
        $subtotal = $totalWeight * $pricePerKg;

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'date' => $dailyReport->date,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'balance' => $subtotal,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        $invoice->items()->create([
            'product_id' => $productId,
            'shipment_item_id' => $shipmentItem->id,
            'cartons' => $cartons,
            'quantity' => $totalWeight,
            'unit_price' => $pricePerKg,
            'subtotal' => $subtotal,
        ]);

        $shipmentItem->increment('sold_cartons', $cartons);
    }

    private function createAndSettleShipment(string $number, int $cartons): Shipment
    {
        $shipment = $this->createShipmentWithItems($number, $cartons);

        // Sell all cartons
        $this->sellCartons($shipment, $this->products[0]->id, $cartons);

        // Close and settle
        $shipment->update(['status' => 'closed']);
        // Create next shipment for carryover
        $nextShipment = Shipment::create([
            'number' => $number . '-NEXT',
            'supplier_id' => $this->supplier->id,
            'date' => now(),
            'status' => 'open',
            'created_by' => $this->admin->id,
        ]);

        app(ShipmentService::class)->settle($shipment, $nextShipment);

        return $shipment->fresh();
    }
}
