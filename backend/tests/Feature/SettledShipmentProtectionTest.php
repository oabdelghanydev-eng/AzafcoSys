<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Exceptions\ErrorCodes;
use App\Models\Shipment;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Settled Shipment Protection
 * تحسين 2025-12-13: اختبارات حماية الشحنات المُصفاة
 */
class SettledShipmentProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that settled shipment fields cannot be modified
     */
    public function test_settled_shipment_fields_cannot_be_modified(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
            'notes' => 'Original notes',
        ]);

        $this->expectException(BusinessException::class);

        $shipment->notes = 'Modified notes';
        $shipment->save();
    }

    /**
     * Test that modification error includes field names
     */
    public function test_modification_error_includes_field_names(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
            'notes' => 'Original',
        ]);

        try {
            $shipment->notes = 'Changed';
            $shipment->save();
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $this->assertEquals(ErrorCodes::SHP_001, $e->getErrorCode());
            $this->assertStringContainsString('notes', $e->getMessageAr());
        }
    }

    /**
     * Test that settled shipment status can only change to closed (unsettle)
     */
    public function test_settled_shipment_can_only_change_to_closed(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
        ]);

        // Try to change to open (should fail)
        try {
            $shipment->status = 'open';
            $shipment->save();
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $this->assertEquals(ErrorCodes::SHP_004, $e->getErrorCode());
        }
    }

    /**
     * Test that settled shipment can be unsettled (status -> closed)
     */
    public function test_settled_shipment_can_be_unsettled(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
        ]);

        // Change to closed (unsettle) should work
        $shipment->status = 'closed';
        $shipment->save();

        $shipment->refresh();
        $this->assertEquals('closed', $shipment->status);
    }

    /**
     * Test that open shipment can be modified freely
     */
    public function test_open_shipment_can_be_modified(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'open',
            'notes' => 'Original',
        ]);

        $shipment->notes = 'Modified';
        $shipment->save();

        $shipment->refresh();
        $this->assertEquals('Modified', $shipment->notes);
    }

    /**
     * Test fifo_sequence is auto-generated on creation
     */
    public function test_fifo_sequence_is_auto_generated(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
        ]);

        $this->assertNotNull($shipment->fifo_sequence);
        $this->assertIsInt($shipment->fifo_sequence);
    }

    /**
     * Test fifo_sequence is immutable (cannot be changed)
     */
    public function test_fifo_sequence_is_immutable(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
        ]);

        $originalSequence = $shipment->fifo_sequence;

        $this->expectException(BusinessException::class);

        $shipment->fifo_sequence = $originalSequence + 100;
        $shipment->save();
    }

    /**
     * Test that closed shipment can be modified
     */
    public function test_closed_shipment_can_be_modified(): void
    {
        $supplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'closed',
            'notes' => 'Original',
        ]);

        $shipment->notes = 'Modified';
        $shipment->save();

        $shipment->refresh();
        $this->assertEquals('Modified', $shipment->notes);
    }

    /**
     * Test that multiple field changes on settled shipment lists all fields
     */
    public function test_multiple_field_changes_lists_all_fields(): void
    {
        $supplier = Supplier::factory()->create();
        $newSupplier = Supplier::factory()->create();
        $shipment = Shipment::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'settled',
            'notes' => 'Original',
        ]);

        try {
            $shipment->notes = 'Changed';
            $shipment->supplier_id = $newSupplier->id;
            $shipment->save();
            $this->fail('Expected BusinessException was not thrown');
        } catch (BusinessException $e) {
            $message = $e->getMessageAr();
            $this->assertStringContainsString('notes', $message);
            $this->assertStringContainsString('supplier_id', $message);
        }
    }
}
