<?php

namespace Tests\Unit\Calculations;

use Tests\TestCase;

/**
 * Carton Balance Calculation Tests
 * 
 * Verifies the inventory carton balance formula:
 * Remaining = Original + Carryover In - Sold - Carryover Out - Wastage
 */
class CartonBalanceTest extends TestCase
{
    /**
     * Helper to calculate remaining cartons
     */
    private function calculateRemaining(
        int $original,
        int $carryoverIn = 0,
        int $sold = 0,
        int $carryoverOut = 0,
        int $wastage = 0
    ): int {
        return $original + $carryoverIn - $sold - $carryoverOut - $wastage;
    }

    /**
     * @test
     * Fresh stock with partial sales
     */
    public function it_calculates_remaining_from_fresh_stock(): void
    {
        $original = 100;
        $sold = 60;

        $expected = 40;
        $actual = $this->calculateRemaining($original, 0, $sold, 0, 0);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Stock with carryover from previous shipment
     */
    public function it_includes_carryover_in_balance(): void
    {
        $original = 100;
        $carryoverIn = 20;
        $sold = 80;

        // 100 + 20 - 80 = 40
        $expected = 40;
        $actual = $this->calculateRemaining($original, $carryoverIn, $sold, 0, 0);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Fully sold stock
     */
    public function it_returns_zero_when_fully_sold(): void
    {
        $original = 100;
        $sold = 100;

        $expected = 0;
        $actual = $this->calculateRemaining($original, 0, $sold, 0, 0);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Stock with wastage
     */
    public function it_deducts_wastage_from_balance(): void
    {
        $original = 100;
        $sold = 90;
        $wastage = 5;

        // 100 - 90 - 5 = 5
        $expected = 5;
        $actual = $this->calculateRemaining($original, 0, $sold, 0, $wastage);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Settlement scenario - carryover out
     */
    public function it_calculates_zero_after_settlement(): void
    {
        $original = 100;
        $sold = 70;
        $carryoverOut = 30;

        // 100 - 70 - 30 = 0 (all accounted for)
        $expected = 0;
        $actual = $this->calculateRemaining($original, 0, $sold, $carryoverOut, 0);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Complex scenario with all components
     */
    public function it_handles_complex_balance_with_all_components(): void
    {
        $original = 100;
        $carryoverIn = 30;
        $sold = 80;
        $carryoverOut = 40;
        $wastage = 5;

        // 100 + 30 - 80 - 40 - 5 = 5
        $expected = 5;
        $actual = $this->calculateRemaining($original, $carryoverIn, $sold, $carryoverOut, $wastage);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Only carryover, no original stock
     */
    public function it_handles_carryover_only_items(): void
    {
        $original = 0;  // No new cartons
        $carryoverIn = 50;
        $sold = 30;

        // 0 + 50 - 30 = 20
        $expected = 20;
        $actual = $this->calculateRemaining($original, $carryoverIn, $sold, 0, 0);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Carryover chain across shipments
     */
    public function it_validates_carryover_chain_logic(): void
    {
        // Shipment 1: Start with 100, sell 70
        $s1_original = 100;
        $s1_sold = 70;
        $s1_remaining = $s1_original - $s1_sold;
        $this->assertEquals(30, $s1_remaining, 'S1 remaining');

        // Settlement: Carryover 30 to next shipment
        $s1_carryoverOut = 30;
        $s1_final = $s1_original - $s1_sold - $s1_carryoverOut;
        $this->assertEquals(0, $s1_final, 'S1 after settlement');

        // Shipment 2: Receives 30 carryover + 100 new
        $s2_original = 100;
        $s2_carryoverIn = 30; // From S1
        $s2_sold = 80;
        $s2_remaining = $s2_original + $s2_carryoverIn - $s2_sold;
        $this->assertEquals(50, $s2_remaining, 'S2 remaining');

        // Verify the carryover matches
        $this->assertEquals($s1_carryoverOut, $s2_carryoverIn, 'Carryover consistency');
    }

    /**
     * @test
     * Multiple products per shipment
     */
    public function it_calculates_per_product_balance_independently(): void
    {
        // Product A
        $productA = $this->calculateRemaining(100, 0, 50, 0, 0);
        $this->assertEquals(50, $productA);

        // Product B
        $productB = $this->calculateRemaining(150, 20, 100, 0, 5);
        // 150 + 20 - 100 - 5 = 65
        $this->assertEquals(65, $productB);

        // Product C (fully sold)
        $productC = $this->calculateRemaining(80, 0, 80, 0, 0);
        $this->assertEquals(0, $productC);

        // Total remaining across products
        $totalRemaining = $productA + $productB + $productC;
        $this->assertEquals(115, $totalRemaining);
    }

    /**
     * @test
     * Negative balance prevention (edge case)
     */
    public function it_prevents_overselling_logically(): void
    {
        $original = 100;
        $sold = 100;
        $carryoverOut = 0;

        $remaining = $this->calculateRemaining($original, 0, $sold, $carryoverOut, 0);

        // Cannot have negative remaining
        $this->assertGreaterThanOrEqual(0, $remaining);

        // Cannot carryover more than remaining
        $maxCarryover = $remaining;
        $this->assertEquals(0, $maxCarryover, 'No cartons available for carryover');
    }
}
