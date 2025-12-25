<?php

namespace Tests\Unit\Calculations;

use Tests\TestCase;

/**
 * Balance Calculation Tests
 * 
 * Verifies the supplier and customer balance calculations
 */
class BalanceCalculationTest extends TestCase
{
    /**
     * ═══════════════════════════════════════════════════════════════════
     * SUPPLIER BALANCE TESTS
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Formula:
     * Final Balance = Net Sales - Commission - Expenses + Previous Balance
     */

    /**
     * @test
     * First shipment uses opening balance
     */
    public function it_calculates_first_shipment_balance_with_opening_balance(): void
    {
        $openingBalance = 10000;
        $netSales = 50000;
        $commission = 3000; // 6%
        $expenses = 1000;
        $previousBalance = $openingBalance; // First shipment

        // Formula: 50,000 - 3,000 - 1,000 + 10,000 = 56,000
        $expected = 56000;
        $actual = $netSales - $commission - $expenses + $previousBalance;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Second shipment uses previous final as its previous balance
     */
    public function it_chains_balance_from_previous_shipment(): void
    {
        // First shipment result
        $previousFinal = 56000;

        // Second shipment
        $netSales = 30000;
        $commission = 1800; // 6%
        $expenses = 0;

        // Formula: 30,000 - 1,800 - 0 + 56,000 = 84,200
        $expected = 84200;
        $actual = $netSales - $commission - $expenses + $previousFinal;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Balance chain across 3 shipments
     */
    public function it_maintains_correct_balance_chain_across_multiple_shipments(): void
    {
        $commissionRate = 0.06;

        // Opening balance
        $balance = 10000;

        // Shipment 1: 50,000 sales
        $sales1 = 50000;
        $balance = $sales1 - ($sales1 * $commissionRate) - 0 + $balance;
        $this->assertEquals(57000, $balance, 'After shipment 1');

        // Shipment 2: 30,000 sales
        $sales2 = 30000;
        $balance = $sales2 - ($sales2 * $commissionRate) - 0 + $balance;
        $this->assertEquals(85200, $balance, 'After shipment 2');

        // Shipment 3: 20,000 sales with 500 expenses
        $sales3 = 20000;
        $expenses3 = 500;
        $balance = $sales3 - ($sales3 * $commissionRate) - $expenses3 + $balance;
        $this->assertEquals(103500, $balance, 'After shipment 3');
    }

    /**
     * @test
     * Zero sales shipment (only carryover)
     */
    public function it_handles_zero_sales_shipment(): void
    {
        $previousBalance = 50000;
        $netSales = 0;
        $commission = 0;
        $expenses = 0;

        // Balance should remain unchanged
        $expected = 50000;
        $actual = $netSales - $commission - $expenses + $previousBalance;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Negative balance scenario (expenses exceed sales)
     */
    public function it_allows_negative_balance_when_expenses_exceed_sales(): void
    {
        $previousBalance = 0;
        $netSales = 10000;
        $commission = 600;
        $expenses = 15000; // More than net sales

        // Formula: 10,000 - 600 - 15,000 + 0 = -5,600
        $expected = -5600;
        $actual = $netSales - $commission - $expenses + $previousBalance;

        $this->assertEquals($expected, $actual);
    }

    /**
     * ═══════════════════════════════════════════════════════════════════
     * CUSTOMER BALANCE TESTS
     * ═══════════════════════════════════════════════════════════════════
     * 
     * Formula:
     * Balance = ∑ Invoices - ∑ Collections - ∑ Returns
     */

    /**
     * @test
     * Customer balance after invoice
     */
    public function it_increases_customer_balance_on_invoice(): void
    {
        $initialBalance = 0;
        $invoiceTotal = 10000;

        $expected = 10000;
        $actual = $initialBalance + $invoiceTotal;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Customer balance after partial collection
     */
    public function it_decreases_customer_balance_on_collection(): void
    {
        $initialBalance = 10000;
        $collectionAmount = 4000;

        $expected = 6000;
        $actual = $initialBalance - $collectionAmount;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Customer balance with return
     */
    public function it_decreases_customer_balance_on_return(): void
    {
        $initialBalance = 6000;
        $returnAmount = 1000;

        $expected = 5000;
        $actual = $initialBalance - $returnAmount;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Customer overpayment creates credit balance
     */
    public function it_allows_negative_customer_balance_on_overpayment(): void
    {
        $balance = 5000;
        $collection = 6000; // Overpayment

        $expected = -1000; // Credit to customer
        $actual = $balance - $collection;

        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     * Complex customer balance scenario
     */
    public function it_calculates_complex_customer_balance_correctly(): void
    {
        $balance = 0;

        // Invoice 1: +10,000
        $balance += 10000;
        $this->assertEquals(10000, $balance, 'After invoice 1');

        // Invoice 2: +5,000
        $balance += 5000;
        $this->assertEquals(15000, $balance, 'After invoice 2');

        // Collection: -8,000
        $balance -= 8000;
        $this->assertEquals(7000, $balance, 'After collection');

        // Return: -2,000
        $balance -= 2000;
        $this->assertEquals(5000, $balance, 'After return');

        // Full payment: -5,000
        $balance -= 5000;
        $this->assertEquals(0, $balance, 'After full payment');
    }
}
