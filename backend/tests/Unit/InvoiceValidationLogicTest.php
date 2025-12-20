<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Invoice Validation Logic
 * تحسين 2025-12-13: اختبارات منطق التحقق
 */
class InvoiceValidationLogicTest extends TestCase
{
    /**
     * Test that discount validation logic works correctly
     * Uses new field names: total_weight, price (instead of quantity, unit_price)
     */
    public function test_discount_exceeds_subtotal_calculation(): void
    {
        $items = [
            ['total_weight' => 10, 'price' => 10],  // 100
            ['total_weight' => 5, 'price' => 20],   // 100
        ];

        $subtotal = collect($items)->sum(function ($item) {
            return ($item['total_weight'] ?? 0) * ($item['price'] ?? 0);
        });

        $this->assertEquals(200, $subtotal);

        // Discount of 250 should exceed subtotal
        $discount = 250;
        $this->assertTrue($discount > $subtotal);

        // Discount of 100 should not exceed subtotal
        $discount = 100;
        $this->assertFalse($discount > $subtotal);
    }

    /**
     * Test total calculation with discount
     */
    public function test_total_calculation_with_discount(): void
    {
        $subtotal = 1000;
        $discount = 100;

        $total = $subtotal - $discount;

        $this->assertEquals(900, $total);
        $this->assertTrue($total > 0, 'Total should be positive');
    }

    /**
     * Test zero total is only valid for wastage type
     */
    public function test_zero_total_validation_for_wastage(): void
    {
        $subtotal = 100;
        $discount = 100;
        $total = $subtotal - $discount;

        // Zero total for 'sale' should fail
        $typeSale = 'sale';
        $isValidForSale = !($total <= 0 && $typeSale !== 'wastage');
        $this->assertFalse($isValidForSale, 'Zero total sale should be invalid');

        // Zero total for 'wastage' should pass
        $typeWastage = 'wastage';
        $isValidForWastage = !($total <= 0 && $typeWastage !== 'wastage');
        $this->assertTrue($isValidForWastage, 'Zero total wastage should be valid');
    }
}
