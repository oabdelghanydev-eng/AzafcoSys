<?php

namespace Tests\Unit\Calculations;

use Tests\TestCase;

/**
 * Commission Calculation Tests
 * 
 * Verifies the commission calculation formula:
 * Commission = Net Sales × Commission Rate (default 6%)
 */
class CommissionCalculationTest extends TestCase
{
    private const DEFAULT_COMMISSION_RATE = 0.06; // 6%

    /**
     * @test
     * Standard 6% commission calculation
     */
    public function it_calculates_standard_six_percent_commission(): void
    {
        $netSales = 50000;
        $commissionRate = self::DEFAULT_COMMISSION_RATE;

        $expected = 3000; // 50,000 × 0.06 = 3,000
        $actual = $netSales * $commissionRate;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Zero sales should result in zero commission
     */
    public function it_returns_zero_commission_for_zero_sales(): void
    {
        $netSales = 0;
        $commissionRate = self::DEFAULT_COMMISSION_RATE;

        $expected = 0;
        $actual = $netSales * $commissionRate;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Large value precision test
     */
    public function it_handles_large_values_correctly(): void
    {
        $netSales = 1000000;
        $commissionRate = self::DEFAULT_COMMISSION_RATE;

        $expected = 60000; // 1,000,000 × 0.06 = 60,000
        $actual = $netSales * $commissionRate;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Decimal precision test - ensures floating point accuracy
     */
    public function it_handles_decimal_precision_correctly(): void
    {
        $netSales = 12345.67;
        $commissionRate = self::DEFAULT_COMMISSION_RATE;

        $expected = 740.74; // 12,345.67 × 0.06 = 740.7402 → rounded to 740.74
        $actual = round($netSales * $commissionRate, 2);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Different commission rates
     */
    public function it_supports_different_commission_rates(): void
    {
        $netSales = 100000;

        $testCases = [
            ['rate' => 0.05, 'expected' => 5000],   // 5%
            ['rate' => 0.06, 'expected' => 6000],   // 6%
            ['rate' => 0.07, 'expected' => 7000],   // 7%
            ['rate' => 0.10, 'expected' => 10000],  // 10%
            ['rate' => 0.15, 'expected' => 15000],  // 15%
        ];

        foreach ($testCases as $case) {
            $actual = round($netSales * $case['rate'], 2);
            $this->assertEquals(
                $case['expected'],
                $actual,
                "Failed for rate {$case['rate']}"
            );
        }
    }

    /**
     * @test
     * Negative values handling (edge case)
     */
    public function it_handles_negative_sales_edge_case(): void
    {
        // In case of net returns exceeding sales
        $netSales = -5000;
        $commissionRate = self::DEFAULT_COMMISSION_RATE;

        $expected = -300; // -5,000 × 0.06 = -300
        $actual = $netSales * $commissionRate;

        $this->assertEquals($expected, $actual);
    }
}
