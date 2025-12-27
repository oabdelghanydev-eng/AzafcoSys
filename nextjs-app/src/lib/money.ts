/**
 * Decimal-safe money handling utilities
 * 
 * Prevents floating-point precision errors in financial calculations.
 * Uses integer arithmetic internally (cents/fils) for exact calculations.
 * 
 * @example
 * // Correct: 0.1 + 0.2 === 0.3
 * moneyEquals(0.1 + 0.2, 0.3) // false with native JS
 * moneyEquals(money(0.1).plus(money(0.2)), money(0.3)) // true with this lib
 */

// Precision: 2 decimal places (cents/fils)
const PRECISION = 2;
const MULTIPLIER = Math.pow(10, PRECISION);

/**
 * Money value wrapper for decimal-safe operations
 */
export class Money {
    private readonly cents: number;

    private constructor(cents: number) {
        this.cents = Math.round(cents);
    }

    /**
     * Create Money from a decimal value (e.g., 10.50)
     */
    static fromDecimal(value: number | string): Money {
        const num = typeof value === 'string' ? parseFloat(value) : value;
        if (isNaN(num)) return new Money(0);
        return new Money(Math.round(num * MULTIPLIER));
    }

    /**
     * Create Money from cents/fils integer
     */
    static fromCents(cents: number): Money {
        return new Money(cents);
    }

    /**
     * Zero value
     */
    static zero(): Money {
        return new Money(0);
    }

    /**
     * Add two money values
     */
    plus(other: Money): Money {
        return new Money(this.cents + other.cents);
    }

    /**
     * Subtract money value
     */
    minus(other: Money): Money {
        return new Money(this.cents - other.cents);
    }

    /**
     * Multiply by a factor (e.g., quantity)
     */
    times(factor: number): Money {
        return new Money(Math.round(this.cents * factor));
    }

    /**
     * Check equality
     */
    equals(other: Money): boolean {
        return this.cents === other.cents;
    }

    /**
     * Check if greater than
     */
    greaterThan(other: Money): boolean {
        return this.cents > other.cents;
    }

    /**
     * Check if less than
     */
    lessThan(other: Money): boolean {
        return this.cents < other.cents;
    }

    /**
     * Check if greater than or equal
     */
    greaterThanOrEqual(other: Money): boolean {
        return this.cents >= other.cents;
    }

    /**
     * Check if less than or equal
     */
    lessThanOrEqual(other: Money): boolean {
        return this.cents <= other.cents;
    }

    /**
     * Check if zero
     */
    isZero(): boolean {
        return this.cents === 0;
    }

    /**
     * Check if negative
     */
    isNegative(): boolean {
        return this.cents < 0;
    }

    /**
     * Get decimal value (for display/API)
     */
    toDecimal(): number {
        return this.cents / MULTIPLIER;
    }

    /**
     * Get cents/fils integer
     */
    toCents(): number {
        return this.cents;
    }

    /**
     * Format as string with 2 decimals
     */
    toFixed(): string {
        return this.toDecimal().toFixed(PRECISION);
    }

    /**
     * Format with thousand separators
     */
    toFormatted(): string {
        return this.toDecimal().toLocaleString('en-US', {
            minimumFractionDigits: PRECISION,
            maximumFractionDigits: PRECISION,
        });
    }
}

// =============================================================================
// Convenience Functions
// =============================================================================

/**
 * Create Money from decimal value
 */
export function money(value: number | string | null | undefined): Money {
    if (value === null || value === undefined) return Money.zero();
    return Money.fromDecimal(value);
}

/**
 * Sum an array of money values
 */
export function sumMoney(values: (number | string | Money)[]): Money {
    return values.reduce<Money>((sum, v) => {
        const m = v instanceof Money ? v : money(v);
        return sum.plus(m);
    }, Money.zero());
}

/**
 * Check if two monetary values are equal (decimal-safe)
 */
export function moneyEquals(
    a: number | string | Money,
    b: number | string | Money
): boolean {
    const moneyA = a instanceof Money ? a : money(a);
    const moneyB = b instanceof Money ? b : money(b);
    return moneyA.equals(moneyB);
}

/**
 * Compare monetary values
 * Returns: -1 if a < b, 0 if a == b, 1 if a > b
 */
export function compareMoney(
    a: number | string | Money,
    b: number | string | Money
): -1 | 0 | 1 {
    const moneyA = a instanceof Money ? a : money(a);
    const moneyB = b instanceof Money ? b : money(b);
    if (moneyA.lessThan(moneyB)) return -1;
    if (moneyA.greaterThan(moneyB)) return 1;
    return 0;
}

/**
 * Validate that an allocation array sums to expected total
 */
export function validateAllocationSum(
    allocations: (number | string)[],
    expectedTotal: number | string
): { valid: boolean; difference: string } {
    const sum = sumMoney(allocations);
    const expected = money(expectedTotal);
    const valid = sum.equals(expected);
    const diff = sum.minus(expected);
    return {
        valid,
        difference: diff.toFixed(),
    };
}
