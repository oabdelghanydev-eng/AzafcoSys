<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for No-Delete Policy (Cartons-Based)
 * Updated 2025-12-19: Uses sold_cartons instead of sold_quantity
 */
class NoDeletePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that invoices cannot be deleted
     */
    public function test_invoice_deletion_throws_exception(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->expectException(BusinessException::class);

        $invoice->delete();
    }

    /**
     * Test that invoice delete returns correct error code
     */
    public function test_invoice_delete_returns_correct_error_code(): void
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
        ]);

        try {
            $invoice->delete();
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $this->assertEquals(ErrorCodes::INVOICE_001, $e->getErrorCode());
        }
    }

    /**
     * Test that collections cannot be deleted
     */
    public function test_collection_deletion_throws_exception(): void
    {
        $customer = Customer::factory()->create();
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->expectException(BusinessException::class);

        $collection->delete();
    }

    /**
     * Test that collection delete returns correct error code
     */
    public function test_collection_delete_returns_correct_error_code(): void
    {
        $customer = Customer::factory()->create();
        $collection = Collection::factory()->create([
            'customer_id' => $customer->id,
        ]);

        try {
            $collection->delete();
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $this->assertEquals(ErrorCodes::COL_001, $e->getErrorCode());
        }
    }

    /**
     * Test that settled shipments cannot be deleted
     */
    public function test_settled_shipment_deletion_throws_exception(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
        ]);

        $this->expectException(BusinessException::class);

        $shipment->delete();
    }

    /**
     * Test that shipment with sales cannot be deleted
     */
    public function test_shipment_with_sales_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        // Add item with sales (sold_cartons > 0)
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'sold_cartons' => 10,
        ]);

        try {
            $shipment->delete();
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $this->assertEquals(ErrorCodes::SHP_002, $e->getErrorCode());
        }
    }

    /**
     * Test that open shipment without sales can be deleted
     */
    public function test_open_shipment_without_sales_can_be_deleted(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
        ]);

        // Add item with no sales
        ShipmentItem::factory()->create([
            'shipment_id' => $shipment->id,
            'sold_cartons' => 0,
        ]);

        // This should not throw
        $result = $shipment->delete();

        $this->assertTrue($result);
        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
    }
}

